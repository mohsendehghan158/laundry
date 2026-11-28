<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Support\Settings;
use App\Modules\Reporting\Http\Requests\AccountingExportRequest;
use App\Modules\Reporting\Http\Requests\ProductionReportRequest;
use App\Modules\Reporting\Http\Requests\ReceivablesRequest;
use App\Modules\Reporting\Http\Requests\SalesReportRequest;
use App\Modules\Reporting\Support\AccountingJournal;
use App\Modules\Reporting\Support\ProductionReport;
use App\Modules\Reporting\Support\Receivables;
use App\Modules\Reporting\Support\Reports;
use App\Support\Export\XlsxWriter;
use App\Support\Text\Jalali;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ReportController
{
    /**
     * Today at a glance
     *
     * Orders and sales taken in today, money collected today, the
     * workshop's load, and the last 14 days. Amounts are whole rials;
     * days follow the organization's time zone.
     *
     * @response array{data: array{today: array{orders: int, pieces: int, sales: int, collected: int}, workload: array{due_today: int, overdue: int, in_process: int, ready: int}, trend: list<array{date: string, orders: int, sales: int}>}}
     */
    #[QueryParameter('branch_id', 'Limit to one branch.', type: 'integer')]
    public function dashboard(Request $request, #[CurrentUser] User $actor, Reports $reports): JsonResponse
    {
        Gate::authorize('reports.view');

        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;

        return response()->json(['data' => $reports->dashboard($reports->branches($actor, $branchId))]);
    }

    /**
     * Sales report
     *
     * Orders taken in between two dates (cancelled ones excluded),
     * grouped by day, service, item, branch or the user who took them
     * in, with totals. "collected" is money received minus refunds in the
     * same period. Branch expenses of the period are alongside: per day or
     * branch row, per category, and "net" = sales without VAT − expenses.
     * At most one year at a time.
     *
     * @response array{data: array{rows: list<array{key: string, label: string, orders: int|null, quantity: float|null, discount: int|null, tax: int|null, total: int, collected: int|null, expenses: int|null}>, totals: array{orders: int, quantity: float|null, lines: int|null, discount: int, tax: int, total: int, collected: int, expenses: int, net: int}, expenses_by_category: list<array{id: int, name: string, total: int}>}}
     */
    public function sales(SalesReportRequest $request, Reports $reports): JsonResponse
    {
        return response()->json(['data' => $this->report($request, $reports)]);
    }

    /**
     * Download the sales report as Excel
     *
     * The same figures as the sales report, in an .xlsx file with Persian
     * headers, Jalali dates and amounts in the display unit.
     */
    public function export(SalesReportRequest $request, Reports $reports, Settings $settings): Response
    {
        $report = $this->report($request, $reports);
        $group = (string) $request->validated('group');
        $toman = $settings->get('general.currency_unit') === 'toman';
        $unit = $toman ? __('reports.toman') : __('reports.rial');
        $money = fn (?int $rial): ?int => $rial === null ? null : ($toman ? intdiv($rial, 10) : $rial);
        $label = fn (string $value): string => $group === 'day'
            ? Jalali::format(CarbonImmutable::parse($value, $reports->timezone()), $reports->timezone(), false)
            : $value;

        $headers = [__("reports.groups.{$group}"), __('reports.orders')];
        if (in_array($group, ['service', 'item'], true)) {
            $headers[] = __('reports.quantity');
        } else {
            $headers[] = __('reports.discount')." ({$unit})";
            $headers[] = __('reports.tax')." ({$unit})";
        }
        $headers[] = __('reports.total')." ({$unit})";
        if ($group === 'day') {
            $headers[] = __('reports.collected')." ({$unit})";
        }
        $withExpenses = in_array($group, ['day', 'branch'], true);
        if ($withExpenses) {
            $headers[] = __('reports.expenses')." ({$unit})";
        }

        $line = function (array $row) use ($group, $money, $withExpenses): array {
            $cells = [$row['orders']];
            if (in_array($group, ['service', 'item'], true)) {
                $cells[] = $row['quantity'];
            } else {
                $cells[] = $money($row['discount']);
                $cells[] = $money($row['tax']);
            }
            $cells[] = $money($row['total']);
            if ($group === 'day') {
                $cells[] = $money($row['collected']);
            }
            if ($withExpenses) {
                $cells[] = $money($row['expenses']);
            }

            return $cells;
        };

        $rows = array_map(fn (array $row): array => [$label($row['label']), ...$line($row)], $report['rows']);
        // By service or item the rows are order lines, so their total is too.
        $rows[] = [__('reports.totals'), ...$line([...$report['totals'], 'total' => $report['totals']['lines'] ?? $report['totals']['total']])];
        if ($report['totals']['expenses'] > 0 || $withExpenses) {
            $rows[] = [__('reports.net')." ({$unit})", $money($report['totals']['net'])];
        }

        $from = (string) $request->validated('from');
        $to = (string) $request->validated('to');
        $bytes = XlsxWriter::build(__('reports.sheet'), $headers, $rows);

        return response($bytes, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="sales-'.$group.'-'.$from.'-'.$to.'.xlsx"',
        ]);
    }

    /**
     * Production report
     *
     * Orders that became ready between two dates: cycle time (intake to
     * ready, in hours), how many were ready by their due time, how long
     * finished orders waited for pickup, the latest ones, and the work at
     * each station and by each person in the period.
     *
     * @response array{data: array{summary: array{ready: int, on_time: int, late: int, on_time_percent: float|null, avg_cycle_hours: float|null, median_cycle_hours: float|null, avg_late_hours: float|null, picked_up: int, avg_pickup_wait_hours: float|null}, by_day: list<array{date: string, ready: int, on_time: int, avg_cycle_hours: float|null}>, by_service: list<array{service: string, orders: int, on_time: int, avg_cycle_hours: float|null}>, stations: list<array{id: int, station: string, branch: string, scans: int, pieces: int, qc_passed: int, qc_failed: int}>, operators: list<array{id: int, name: string, scans: int, pieces: int}>, late_orders: list<array{id: int, ticket_number: string, customer_name: string, due_at: string, ready_at: string, hours_late: float}>}}
     */
    public function production(ProductionReportRequest $request, Reports $reports, ProductionReport $report): JsonResponse
    {
        $branchId = $request->validated('branch_id');

        return response()->json(['data' => $report->build(
            $reports->branches($request->actor(), $branchId === null ? null : (int) $branchId),
            (string) $request->validated('from'),
            (string) $request->validated('to'),
        )]);
    }

    /**
     * Receivables aging
     *
     * What every customer, or company for orders charged to one, still
     * owes, by days past due: current, 1-30, 31-60, 61-90 and over 90.
     * Orders are due when taken in, or after the company's payment terms.
     *
     * @response array{data: array{as_of: string, rows: list<array{key: string, kind: 'company'|'customer', id: int, name: string, mobile: string|null, orders: int, current: int, d30: int, d60: int, d90: int, over90: int, total: int, oldest_days: int}>, totals: array{orders: int, current: int, d30: int, d60: int, d90: int, over90: int, total: int}}}
     */
    public function receivables(ReceivablesRequest $request, Reports $reports, Receivables $receivables): JsonResponse
    {
        $branchId = $request->validated('branch_id');

        return response()->json(['data' => $receivables->aging($reports->branches($request->actor(), $branchId === null ? null : (int) $branchId))]);
    }

    /**
     * Download the receivables aging as Excel
     *
     * Amounts in the display unit.
     */
    public function receivablesExport(ReceivablesRequest $request, Reports $reports, Receivables $receivables, Settings $settings): Response
    {
        $branchId = $request->validated('branch_id');
        $aging = $receivables->aging($reports->branches($request->actor(), $branchId === null ? null : (int) $branchId));
        $toman = $settings->get('general.currency_unit') === 'toman';
        $unit = $toman ? __('reports.toman') : __('reports.rial');
        $money = fn (int $rial): int => $toman ? intdiv($rial, 10) : $rial;

        $headers = [__('reports.aging.name'), __('reports.aging.mobile'), __('reports.orders')];
        foreach ([...Receivables::BUCKETS, 'total'] as $column) {
            $headers[] = __("reports.aging.{$column}")." ({$unit})";
        }
        $headers[] = __('reports.aging.oldest_days');

        $rows = array_map(fn (array $row): array => [
            $row['name'], $row['mobile'], $row['orders'],
            ...array_map(fn (string $column): int => $money($row[$column]), [...Receivables::BUCKETS, 'total']),
            $row['oldest_days'],
        ], $aging['rows']);
        $rows[] = [__('reports.totals'), null, $aging['totals']['orders'], ...array_map(fn (string $column): int => $money($aging['totals'][$column]), [...Receivables::BUCKETS, 'total']), null];

        return response(XlsxWriter::build(__('reports.aging.sheet'), $headers, $rows), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="receivables-'.$aging['as_of'].'.xlsx"',
        ]);
    }

    /**
     * Download an accounting journal as Excel
     *
     * Every order taken in, payment, refund and expense of the period as
     * balanced journal lines (date, document, description, account code
     * and name, debit, credit) in rials, for import into accounting
     * software. Same period rules as the sales report.
     */
    public function accountingExport(AccountingExportRequest $request, Reports $reports, AccountingJournal $journal): Response
    {
        $branchId = $request->validated('branch_id');
        $from = (string) $request->validated('from');
        $to = (string) $request->validated('to');
        $entries = $journal->entries($reports->branches($request->actor(), $branchId === null ? null : (int) $branchId), $from, $to);

        $headers = [__('reports.journal.date'), __('reports.journal.document'), __('reports.journal.description'), __('reports.journal.code'), __('reports.journal.account'), __('reports.journal.debit'), __('reports.journal.credit')];
        $rows = array_map(fn (array $entry): array => [$entry['date'], $entry['document'], $entry['description'], $entry['code'], $entry['account'], $entry['debit'], $entry['credit']], $entries);
        $rows[] = [__('reports.totals'), null, null, null, null, array_sum(array_column($entries, 'debit')), array_sum(array_column($entries, 'credit'))];

        return response(XlsxWriter::build(__('reports.journal.sheet'), $headers, $rows), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="journal-'.$from.'-'.$to.'.xlsx"',
        ]);
    }

    /**
     * @return array{rows: list<array{key: string, label: string, orders: int|null, quantity: float|null, discount: int|null, tax: int|null, total: int, collected: int|null, expenses: int|null}>, totals: array{orders: int, quantity: float|null, lines: int|null, discount: int, tax: int, total: int, collected: int, expenses: int, net: int}, expenses_by_category: list<array{id: int, name: string, total: int}>}
     */
    private function report(SalesReportRequest $request, Reports $reports): array
    {
        $branchId = $request->validated('branch_id');

        return $reports->sales(
            $reports->branches($request->actor(), $branchId === null ? null : (int) $branchId),
            (string) $request->validated('from'),
            (string) $request->validated('to'),
            (string) $request->validated('group'),
        );
    }
}
