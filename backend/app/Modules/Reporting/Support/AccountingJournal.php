<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Support;

use App\Support\Text\Jalali;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A general journal of a period for import into accounting software, in
 * rials: each order taken in (receivable against sales, delivery and
 * VAT), each payment and refund (cash or bank against the receivable)
 * and each expense (expense against cash or bank). Every document
 * balances. Account codes are a common Iranian chart and can be mapped
 * on import.
 *
 * @phpstan-type Entry array{date: string, document: string, description: string, code: string, account: string, debit: int, credit: int}
 */
final class AccountingJournal
{
    public const array ACCOUNTS = [
        'cash' => '1101',
        'bank' => '1102',
        'receivable' => '1103',
        'vat' => '2201',
        'sales' => '4101',
        'delivery' => '4102',
        'expense' => '6101',
    ];

    public function __construct(private readonly Reports $reports) {}

    /**
     * @param  list<int>  $branches
     * @return list<Entry>
     */
    public function entries(array $branches, string $from, string $to): array
    {
        $tz = $this->reports->timezone();
        $start = CarbonImmutable::parse($from, $tz)->startOfDay()->utc();
        $end = CarbonImmutable::parse($to, $tz)->endOfDay()->utc();
        $date = fn (string $moment): string => Jalali::format(CarbonImmutable::parse($moment), $tz, false);
        $entries = [];

        $orders = DB::table('orders as o')
            ->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->leftJoin('companies as co', 'co.id', '=', 'o.company_id')
            ->whereIn('o.branch_id', $branches)
            ->where('o.status', '!=', 'cancelled')
            ->whereBetween('o.received_at', [$start, $end])
            ->orderBy('o.received_at')
            ->get(['o.ticket_number', 'o.received_at', 'o.total', 'o.tax_amount', 'o.delivery_fee', 'c.name as customer', 'co.name as company']);

        foreach ($orders as $order) {
            $who = (string) ($order->company ?? $order->customer);
            $doc = (string) $order->ticket_number;
            $when = $date((string) $order->received_at);
            $description = __('reports.journal.order', ['ticket' => $doc, 'name' => $who]);
            $sales = (int) $order->total - (int) $order->tax_amount - (int) $order->delivery_fee;

            $entries[] = $this->entry($when, $doc, $description, 'receivable', debit: (int) $order->total, detail: $who);
            $entries[] = $this->entry($when, $doc, $description, 'sales', credit: $sales);
            if ((int) $order->delivery_fee > 0) {
                $entries[] = $this->entry($when, $doc, $description, 'delivery', credit: (int) $order->delivery_fee);
            }
            if ((int) $order->tax_amount > 0) {
                $entries[] = $this->entry($when, $doc, $description, 'vat', credit: (int) $order->tax_amount);
            }
        }

        $payments = DB::table('payments as p')
            ->join('orders as o', 'o.id', '=', 'p.order_id')
            ->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->leftJoin('companies as co', 'co.id', '=', 'o.company_id')
            ->whereIn('p.branch_id', $branches)
            ->whereBetween('p.created_at', [$start, $end])
            ->orderBy('p.created_at')
            ->get(['p.kind', 'p.method', 'p.amount', 'p.created_at', 'o.ticket_number', 'c.name as customer', 'co.name as company']);

        foreach ($payments as $payment) {
            $who = (string) ($payment->company ?? $payment->customer);
            $doc = (string) $payment->ticket_number;
            $when = $date((string) $payment->created_at);
            $money = $payment->method === 'cash' ? 'cash' : 'bank';
            $amount = (int) $payment->amount;

            if ($payment->kind === 'refund') {
                $description = __('reports.journal.refund', ['ticket' => $doc, 'name' => $who]);
                $entries[] = $this->entry($when, $doc, $description, 'receivable', debit: $amount, detail: $who);
                $entries[] = $this->entry($when, $doc, $description, $money, credit: $amount);
            } else {
                $description = __('reports.journal.payment', ['ticket' => $doc, 'name' => $who, 'method' => __("reports.journal.methods.{$payment->method}")]);
                $entries[] = $this->entry($when, $doc, $description, $money, debit: $amount);
                $entries[] = $this->entry($when, $doc, $description, 'receivable', credit: $amount, detail: $who);
            }
        }

        $expenses = DB::table('expenses as e')
            ->join('expense_categories as c', 'c.id', '=', 'e.expense_category_id')
            ->whereIn('e.branch_id', $branches)
            ->whereNull('e.deleted_at')
            ->whereBetween('e.spent_on', [$from, $to])
            ->orderBy('e.spent_on')
            ->orderBy('e.id')
            ->get(['e.id', 'e.spent_on', 'e.amount', 'e.method', 'e.description', 'e.reference', 'c.name as category']);

        foreach ($expenses as $expense) {
            $doc = (string) ($expense->reference ?? __('reports.journal.expense_doc', ['id' => $expense->id]));
            $when = Jalali::format(CarbonImmutable::parse((string) $expense->spent_on, $tz), $tz, false);
            $description = __('reports.journal.expense', ['category' => $expense->category, 'description' => $expense->description]);
            $amount = (int) $expense->amount;

            $entries[] = $this->entry($when, $doc, $description, 'expense', debit: $amount, detail: (string) $expense->category);
            $entries[] = $this->entry($when, $doc, $description, $expense->method === 'cash' ? 'cash' : 'bank', credit: $amount);
        }

        return $entries;
    }

    /**
     * @return Entry
     */
    private function entry(string $date, string $document, string $description, string $account, int $debit = 0, int $credit = 0, ?string $detail = null): array
    {
        $name = (string) __("reports.journal.accounts.{$account}");

        return [
            'date' => $date,
            'document' => $document,
            'description' => $description,
            'code' => self::ACCOUNTS[$account],
            'account' => $detail === null ? $name : "{$name} – {$detail}",
            'debit' => $debit,
            'credit' => $credit,
        ];
    }
}
