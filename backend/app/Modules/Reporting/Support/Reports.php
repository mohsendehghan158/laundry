<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Support;

use App\Modules\Billing\Enums\PaymentKind;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Figures for the dashboard and the sales report. Days are the
 * organization's local days (its time zone), and only branches the user
 * works in are counted. Amounts are whole rials. Cancelled orders do not
 * count as sales.
 *
 * @phpstan-type SalesRow array{key: string, label: string, orders: int|null, quantity: float|null, discount: int|null, tax: int|null, total: int, collected: int|null, expenses: int|null}
 */
final class Reports
{
    public const array GROUPS = ['day', 'service', 'item', 'branch', 'user'];

    public function __construct(private readonly Settings $settings) {}

    public function timezone(): string
    {
        return (string) $this->settings->get('general.timezone');
    }

    /**
     * @param  int|null  $branchId  limit to one branch (must be one the user works in)
     * @return list<int>
     */
    public function branches(User $actor, ?int $branchId): array
    {
        $ids = array_values(array_map(intval(...), Branch::query()->visibleTo($actor)->pluck('id')->all()));

        return $branchId === null ? $ids : array_values(array_intersect($ids, [$branchId]));
    }

    /**
     * @param  list<int>  $branches
     * @return array{today: array{orders: int, pieces: int, sales: int, collected: int}, workload: array{due_today: int, overdue: int, in_process: int, ready: int}, trend: list<array{date: string, orders: int, sales: int}>}
     */
    public function dashboard(array $branches): array
    {
        $tz = $this->timezone();
        $now = CarbonImmutable::now($tz);
        [$start, $end] = [$now->startOfDay()->utc(), $now->endOfDay()->utc()];
        $open = ['received', 'in_process', 'partially_ready', 'ready'];

        $todayOrders = $this->orders($branches)->whereBetween('o.received_at', [$start, $end])->where('o.status', '!=', OrderStatus::Cancelled->value);

        $trendStart = $now->subDays(13)->startOfDay();
        $daily = $this->orders($branches)
            ->where('o.status', '!=', OrderStatus::Cancelled->value)
            ->where('o.received_at', '>=', $trendStart->utc())
            ->selectRaw($this->localDate('o.received_at').' as day, count(*) as orders, coalesce(sum(o.total), 0) as sales')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $trend = [];
        for ($i = 0; $i < 14; $i++) {
            $day = $trendStart->addDays($i)->format('Y-m-d');
            $trend[] = ['date' => $day, 'orders' => (int) ($daily[$day]->orders ?? 0), 'sales' => (int) ($daily[$day]->sales ?? 0)];
        }

        return [
            'today' => [
                'orders' => (clone $todayOrders)->count(),
                'pieces' => (int) DB::table('pieces')->whereIn('order_id', (clone $todayOrders)->select('o.id'))->count(),
                'sales' => (int) (clone $todayOrders)->sum('o.total'),
                'collected' => $this->collected($branches, $start, $end),
            ],
            'workload' => [
                'due_today' => $this->orders($branches)->whereIn('o.status', $open)->whereBetween('o.due_at', [$start, $end])->count(),
                'overdue' => $this->orders($branches)->whereIn('o.status', $open)->where('o.due_at', '<', now())->count(),
                'in_process' => $this->orders($branches)->whereIn('o.status', ['received', 'in_process'])->count(),
                'ready' => $this->orders($branches)->whereIn('o.status', ['ready', 'partially_ready'])->count(),
            ],
            'trend' => $trend,
        ];
    }

    /**
     * Sales between two local dates (inclusive), grouped by day, service,
     * item, branch or the user who took the order in. Branch expenses of
     * the same days sit next to the sales: per row by day and by branch,
     * per category, and as a net figure (sales without VAT − expenses).
     *
     * @param  list<int>  $branches
     * @return array{rows: list<SalesRow>, totals: array{orders: int, quantity: float|null, lines: int|null, discount: int, tax: int, total: int, collected: int, expenses: int, net: int}, expenses_by_category: list<array{id: int, name: string, total: int}>}
     */
    public function sales(array $branches, string $from, string $to, string $group): array
    {
        $tz = $this->timezone();
        $start = CarbonImmutable::parse($from, $tz)->startOfDay()->utc();
        $end = CarbonImmutable::parse($to, $tz)->endOfDay()->utc();
        $sold = fn (): Builder => $this->orders($branches)->whereBetween('o.received_at', [$start, $end])->where('o.status', '!=', OrderStatus::Cancelled->value);
        $spent = fn (): Builder => DB::table('expenses as e')->whereIn('e.branch_id', $branches)->whereNull('e.deleted_at')->whereBetween('e.spent_on', [$from, $to]);

        $rows = match ($group) {
            'day' => $this->byDay($sold(), $branches, $start, $end, $spent()),
            'service', 'item' => $sold()
                ->join('order_lines as l', 'l.order_id', '=', 'o.id')
                ->selectRaw(($group === 'service' ? 'l.service_name' : 'l.item_name').' as label, count(distinct o.id) as orders, sum(l.quantity) as quantity, sum(l.line_total) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get()
                ->map(fn (object $row): array => $this->row((string) $row->label, (string) $row->label, (int) $row->orders, (float) $row->quantity, null, null, (int) $row->total, null))
                ->all(),
            'branch' => $this->withBranchExpenses(array_values($sold()
                ->join('branches as b', 'b.id', '=', 'o.branch_id')
                ->selectRaw('b.id, b.name as label, count(*) as orders, sum(o.discount_amount) as discount, sum(o.tax_amount) as tax, sum(o.total) as total')
                ->groupBy('b.id', 'b.name')
                ->orderByDesc('total')
                ->get()
                ->map(fn (object $row): array => $this->row((string) $row->id, (string) $row->label, (int) $row->orders, null, (int) $row->discount, (int) $row->tax, (int) $row->total, null))
                ->all()), $spent()),
            'user' => $sold()
                ->leftJoin('users as u', 'u.id', '=', 'o.created_by')
                ->selectRaw("coalesce(u.id, 0) as id, coalesce(u.name, '—') as label, count(*) as orders, sum(o.discount_amount) as discount, sum(o.tax_amount) as tax, sum(o.total) as total")
                ->groupByRaw('coalesce(u.id, 0), coalesce(u.name, \'—\')')
                ->orderByDesc('total')
                ->get()
                ->map(fn (object $row): array => $this->row((string) $row->id, (string) $row->label, (int) $row->orders, null, (int) $row->discount, (int) $row->tax, (int) $row->total, null))
                ->all(),
            default => throw new InvalidArgumentException("Unknown sales grouping: {$group}"),
        };

        $byLine = in_array($group, ['service', 'item'], true);
        $totals = $sold()->selectRaw('count(*) as orders, coalesce(sum(o.discount_amount), 0) as discount, coalesce(sum(o.tax_amount), 0) as tax, coalesce(sum(o.total), 0) as total')->first();

        $byCategory = $spent()
            ->join('expense_categories as c', 'c.id', '=', 'e.expense_category_id')
            ->selectRaw('c.id, c.name, sum(e.amount) as total')
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name, 'total' => (int) $row->total])
            ->all();
        $expenses = array_sum(array_column($byCategory, 'total'));

        return [
            'rows' => array_values($rows),
            'expenses_by_category' => array_values($byCategory),
            'totals' => [
                'orders' => (int) ($totals->orders ?? 0),
                'quantity' => $byLine ? array_sum(array_map(fn (array $row): float => (float) $row['quantity'], $rows)) : null,
                // Order lines before the order's discount and tax: what the rows add up to.
                'lines' => $byLine ? array_sum(array_column($rows, 'total')) : null,
                'discount' => (int) ($totals->discount ?? 0),
                'tax' => (int) ($totals->tax ?? 0),
                'total' => (int) ($totals->total ?? 0),
                'collected' => $this->collected($branches, $start, $end),
                'expenses' => $expenses,
                // Sales without VAT, less expenses.
                'net' => (int) ($totals->total ?? 0) - (int) ($totals->tax ?? 0) - $expenses,
            ],
        ];
    }

    /**
     * Adds each branch's expenses to its row, and rows for branches that
     * only spent money.
     *
     * @param  list<SalesRow>  $rows
     * @return list<SalesRow>
     */
    private function withBranchExpenses(array $rows, Builder $spent): array
    {
        $byBranch = $spent
            ->join('branches as b', 'b.id', '=', 'e.branch_id')
            ->selectRaw('b.id, b.name, sum(e.amount) as total')
            ->groupBy('b.id', 'b.name')
            ->get()
            ->keyBy('id');

        foreach ($rows as $i => $row) {
            $rows[$i]['expenses'] = (int) ($byBranch[(int) $row['key']]->total ?? 0);
            unset($byBranch[(int) $row['key']]);
        }

        foreach ($byBranch as $branch) {
            $rows[] = $this->row((string) $branch->id, (string) $branch->name, 0, null, 0, 0, 0, null, (int) $branch->total);
        }

        return $rows;
    }

    /**
     * @param  list<int>  $branches
     * @return list<SalesRow>
     */
    private function byDay(Builder $sold, array $branches, CarbonImmutable $start, CarbonImmutable $end, Builder $spent): array
    {
        $expenses = $spent
            ->selectRaw("to_char(e.spent_on, 'YYYY-MM-DD') as day, sum(e.amount) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        $orders = $sold
            ->selectRaw($this->localDate('o.received_at').' as day, count(*) as orders, sum(o.discount_amount) as discount, sum(o.tax_amount) as tax, sum(o.total) as total')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $collected = DB::table('payments')
            ->whereIn('branch_id', $branches)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw($this->localDate('created_at')." as day, sum(case when kind = 'refund' then -amount else amount end) as collected")
            ->groupBy('day')
            ->pluck('collected', 'day');

        $rows = [];
        $tz = $this->timezone();
        for ($day = $start->setTimezone($tz)->startOfDay(); $day->lessThanOrEqualTo($end->setTimezone($tz)); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $order = $orders[$key] ?? null;

            if ($order === null && ! isset($collected[$key]) && ! isset($expenses[$key])) {
                continue;
            }

            $rows[] = $this->row($key, $key, (int) ($order->orders ?? 0), null, (int) ($order->discount ?? 0), (int) ($order->tax ?? 0), (int) ($order->total ?? 0), (int) ($collected[$key] ?? 0), (int) ($expenses[$key] ?? 0));
        }

        return $rows;
    }

    /**
     * @param  list<int>  $branches
     */
    private function orders(array $branches): Builder
    {
        return DB::table('orders as o')->whereIn('o.branch_id', $branches);
    }

    /**
     * @param  list<int>  $branches
     */
    private function collected(array $branches, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return (int) DB::table('payments')
            ->whereIn('branch_id', $branches)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("coalesce(sum(case when kind = '".PaymentKind::Refund->value."' then -amount else amount end), 0) as collected")
            ->value('collected');
    }

    /**
     * SQL for the local calendar date of a UTC timestamp column.
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    private function localDate(string $column): string
    {
        $tz = DB::getPdo()->quote($this->timezone());

        // The zone is quoted by PDO, so the expression is safe to embed.
        /** @var literal-string $sql */
        $sql = "to_char(({$column} at time zone 'UTC') at time zone {$tz}, 'YYYY-MM-DD')";

        return $sql;
    }

    /**
     * @return SalesRow
     */
    private function row(string $key, string $label, ?int $orders, ?float $quantity, ?int $discount, ?int $tax, int $total, ?int $collected, ?int $expenses = null): array
    {
        return compact('key', 'label', 'orders', 'quantity', 'discount', 'tax', 'total', 'collected', 'expenses');
    }
}
