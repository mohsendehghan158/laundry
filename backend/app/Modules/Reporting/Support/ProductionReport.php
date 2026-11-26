<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Support;

use App\Modules\Orders\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * How the workshop performed over a period: how long orders took from
 * intake to ready (cycle time), how many were ready by their due time,
 * how long finished orders waited for pickup, and the work done at each
 * station and by each person. Orders count in the period they became
 * ready, for the branch that took them in and for every branch that
 * worked on them (a plant sees the counters' orders it processed); days
 * are the organization's local days.
 *
 * @phpstan-type Summary array{ready: int, on_time: int, late: int, on_time_percent: float|null, avg_cycle_hours: float|null, median_cycle_hours: float|null, avg_late_hours: float|null, picked_up: int, avg_pickup_wait_hours: float|null}
 * @phpstan-type Production array{summary: Summary, by_day: list<array{date: string, ready: int, on_time: int, avg_cycle_hours: float|null}>, by_service: list<array{service: string, orders: int, on_time: int, avg_cycle_hours: float|null}>, stations: list<array{id: int, station: string, branch: string, scans: int, pieces: int, qc_passed: int, qc_failed: int}>, operators: list<array{id: int, name: string, scans: int, pieces: int}>, late_orders: list<array{id: int, ticket_number: string, customer_name: string, due_at: string, ready_at: string, hours_late: float}>}
 */
final class ProductionReport
{
    public function __construct(private readonly Reports $reports) {}

    /**
     * @param  list<int>  $branches
     * @return Production
     */
    public function build(array $branches, string $from, string $to): array
    {
        $tz = $this->reports->timezone();
        $start = CarbonImmutable::parse($from, $tz)->startOfDay()->utc();
        $end = CarbonImmutable::parse($to, $tz)->endOfDay()->utc();
        $ours = fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q
            ->whereIn('o.branch_id', $branches)
            ->orWhereExists(fn (Builder $scans) => $scans->selectRaw('1')->from('piece_scans as ps')->whereColumn('ps.order_id', 'o.id')->whereIn('ps.branch_id', $branches)));
        $ready = fn (): Builder => $ours(DB::table('orders as o'))
            ->where('o.status', '!=', OrderStatus::Cancelled->value)
            ->whereNotNull('o.ready_at')
            ->whereBetween('o.ready_at', [$start, $end]);
        $cycle = 'extract(epoch from (o.ready_at - o.received_at)) / 3600';

        $summary = $ready()->selectRaw("count(*) as ready,
            coalesce(sum(case when o.ready_at <= o.due_at then 1 else 0 end), 0) as on_time,
            avg({$cycle}) as avg_cycle,
            percentile_cont(0.5) within group (order by {$cycle}) as median_cycle,
            avg(case when o.ready_at > o.due_at then extract(epoch from (o.ready_at - o.due_at)) / 3600 end) as avg_late")->first();

        $pickup = $ours(DB::table('orders as o'))
            ->where('o.status', OrderStatus::Completed->value)
            ->whereNotNull('o.ready_at')
            ->whereBetween('o.completed_at', [$start, $end])
            ->selectRaw('count(*) as picked_up, avg(extract(epoch from (o.completed_at - o.ready_at)) / 3600) as wait')
            ->first();

        $readyCount = (int) ($summary->ready ?? 0);
        $onTime = (int) ($summary->on_time ?? 0);

        $byDay = $ready()
            ->selectRaw($this->localDate('o.ready_at')." as day, count(*) as ready, sum(case when o.ready_at <= o.due_at then 1 else 0 end) as on_time, avg({$cycle}) as avg_cycle")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn (object $row): array => ['date' => (string) $row->day, 'ready' => (int) $row->ready, 'on_time' => (int) $row->on_time, 'avg_cycle_hours' => $this->hours($row->avg_cycle)])
            ->all();

        $perService = $ready()
            ->join('order_lines as l', 'l.order_id', '=', 'o.id')
            ->selectRaw("distinct o.id, l.service_name, case when o.ready_at <= o.due_at then 1 else 0 end as on_time, {$cycle} as cycle");
        $byService = DB::query()->fromSub($perService, 't')
            ->selectRaw('service_name, count(*) as orders, sum(on_time) as on_time, avg(cycle) as avg_cycle')
            ->groupBy('service_name')
            ->orderByDesc('orders')
            ->get()
            ->map(fn (object $row): array => ['service' => (string) $row->service_name, 'orders' => (int) $row->orders, 'on_time' => (int) $row->on_time, 'avg_cycle_hours' => $this->hours($row->avg_cycle)])
            ->all();

        $stations = DB::table('piece_scans as s')
            ->join('stations as st', 'st.id', '=', 's.station_id')
            ->join('branches as b', 'b.id', '=', 'st.branch_id')
            ->whereIn('st.branch_id', $branches)
            ->whereBetween('s.created_at', [$start, $end])
            ->selectRaw("st.id, st.name as station, b.name as branch, count(*) as scans, count(distinct s.piece_id) as pieces,
                sum(case when s.event = 'qc_passed' then 1 else 0 end) as qc_passed,
                sum(case when s.event = 'qc_failed' then 1 else 0 end) as qc_failed")
            ->groupBy('st.id', 'st.name', 'b.name', 'st.branch_id', 'st.sort_order')
            ->orderBy('st.branch_id')
            ->orderBy('st.sort_order')
            ->get()
            ->map(fn (object $row): array => ['id' => (int) $row->id, 'station' => (string) $row->station, 'branch' => (string) $row->branch, 'scans' => (int) $row->scans, 'pieces' => (int) $row->pieces, 'qc_passed' => (int) $row->qc_passed, 'qc_failed' => (int) $row->qc_failed])
            ->all();

        $operators = DB::table('piece_scans as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->whereIn('s.branch_id', $branches)
            ->whereBetween('s.created_at', [$start, $end])
            ->selectRaw('u.id, u.name, count(*) as scans, count(distinct s.piece_id) as pieces')
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('scans')
            ->limit(20)
            ->get()
            ->map(fn (object $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name, 'scans' => (int) $row->scans, 'pieces' => (int) $row->pieces])
            ->all();

        $late = $ready()
            ->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->whereColumn('o.ready_at', '>', 'o.due_at')
            ->selectRaw('o.id, o.ticket_number, c.name as customer_name, o.due_at, o.ready_at, extract(epoch from (o.ready_at - o.due_at)) / 3600 as hours_late')
            ->orderByDesc('hours_late')
            ->limit(50)
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'ticket_number' => (string) $row->ticket_number,
                'customer_name' => (string) $row->customer_name,
                'due_at' => CarbonImmutable::parse((string) $row->due_at, 'UTC')->toIso8601String(),
                'ready_at' => CarbonImmutable::parse((string) $row->ready_at, 'UTC')->toIso8601String(),
                'hours_late' => (float) $this->hours($row->hours_late),
            ])
            ->all();

        return [
            'summary' => [
                'ready' => $readyCount,
                'on_time' => $onTime,
                'late' => $readyCount - $onTime,
                'on_time_percent' => $readyCount === 0 ? null : round($onTime * 100 / $readyCount, 1),
                'avg_cycle_hours' => $this->hours($summary->avg_cycle ?? null),
                'median_cycle_hours' => $this->hours($summary->median_cycle ?? null),
                'avg_late_hours' => $this->hours($summary->avg_late ?? null),
                'picked_up' => (int) ($pickup->picked_up ?? 0),
                'avg_pickup_wait_hours' => $this->hours($pickup->wait ?? null),
            ],
            'by_day' => array_values($byDay),
            'by_service' => array_values($byService),
            'stations' => array_values($stations),
            'operators' => array_values($operators),
            'late_orders' => array_values($late),
        ];
    }

    private function hours(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 1);
    }

    /**
     * @param  literal-string  $column
     * @return literal-string
     */
    private function localDate(string $column): string
    {
        $tz = DB::getPdo()->quote($this->reports->timezone());

        // The zone is quoted by PDO, so the expression is safe to embed.
        /** @var literal-string $sql */
        $sql = "to_char(({$column} at time zone 'UTC') at time zone {$tz}, 'YYYY-MM-DD')";

        return $sql;
    }
}
