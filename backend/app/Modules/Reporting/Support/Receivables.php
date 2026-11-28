<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Who owes what, and for how long. An order is due when it is taken in,
 * or after the company's payment terms for orders charged to a company;
 * what is still unpaid is put in a bucket by how many days it is past
 * due. Company orders are grouped per company, the rest per customer.
 *
 * @phpstan-type AgingRow array{key: string, kind: 'company'|'customer', id: int, name: string, mobile: string|null, orders: int, current: int, d30: int, d60: int, d90: int, over90: int, total: int, oldest_days: int}
 */
final class Receivables
{
    public const array BUCKETS = ['current', 'd30', 'd60', 'd90', 'over90'];

    public function __construct(private readonly Reports $reports) {}

    /**
     * @param  list<int>  $branches
     * @return array{as_of: string, rows: list<AgingRow>, totals: array{orders: int, current: int, d30: int, d60: int, d90: int, over90: int, total: int}}
     */
    public function aging(array $branches): array
    {
        $tz = $this->reports->timezone();
        $today = CarbonImmutable::now($tz)->startOfDay();

        $orders = DB::table('orders as o')
            ->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->leftJoin('companies as co', 'co.id', '=', 'o.company_id')
            ->whereIn('o.branch_id', $branches)
            ->where('o.status', '!=', 'cancelled')
            ->whereNotNull('o.received_at')
            ->whereRaw('o.total > o.paid_amount')
            ->select(['o.id', 'o.total', 'o.paid_amount', 'o.received_at', 'o.company_id', 'co.name as company_name', 'co.payment_terms_days', 'c.id as customer_id', 'c.name as customer_name', 'c.mobile'])
            ->get();

        $rows = [];
        foreach ($orders as $order) {
            $due = CarbonImmutable::parse((string) $order->received_at)->setTimezone($tz)->startOfDay()->addDays((int) ($order->payment_terms_days ?? 0));
            $late = (int) max(0, $due->diffInDays($today, false));
            $bucket = match (true) {
                $late === 0 => 'current',
                $late <= 30 => 'd30',
                $late <= 60 => 'd60',
                $late <= 90 => 'd90',
                default => 'over90',
            };
            $owed = (int) $order->total - (int) $order->paid_amount;
            $company = $order->company_id !== null;
            $key = $company ? "company:{$order->company_id}" : "customer:{$order->customer_id}";

            $rows[$key] ??= [
                'key' => $key,
                'kind' => $company ? 'company' : 'customer',
                'id' => (int) ($company ? $order->company_id : $order->customer_id),
                'name' => (string) ($company ? $order->company_name : $order->customer_name),
                'mobile' => $company ? null : (string) $order->mobile,
                'orders' => 0, 'current' => 0, 'd30' => 0, 'd60' => 0, 'd90' => 0, 'over90' => 0, 'total' => 0, 'oldest_days' => 0,
            ];
            $rows[$key]['orders']++;
            $rows[$key][$bucket] += $owed;
            $rows[$key]['total'] += $owed;
            $rows[$key]['oldest_days'] = max($rows[$key]['oldest_days'], $late);
        }

        $rows = array_values($rows);
        usort($rows, fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        $totals = ['orders' => array_sum(array_column($rows, 'orders'))];
        foreach ([...self::BUCKETS, 'total'] as $column) {
            $totals[$column] = array_sum(array_column($rows, $column));
        }

        /** @var array{orders: int, current: int, d30: int, d60: int, d90: int, over90: int, total: int} $totals */
        return ['as_of' => $today->format('Y-m-d'), 'rows' => $rows, 'totals' => $totals];
    }
}
