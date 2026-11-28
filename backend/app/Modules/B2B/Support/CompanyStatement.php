<?php

declare(strict_types=1);

namespace App\Modules\B2B\Support;

use App\Modules\B2B\Enums\DeliveryNoteType;
use App\Modules\B2B\Models\Company;
use App\Modules\B2B\Models\DeliveryNote;
use App\Modules\B2B\Models\DeliveryNoteLine;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Organization\Support\Settings;
use Carbon\CarbonImmutable;

/**
 * A company's statement for a period: the orders charged to it (taken in
 * between two local dates, cancelled ones aside) with the delivery notes
 * behind them, per department, what was paid, and what was still owed
 * from before. Amounts are whole rials.
 *
 * @phpstan-type StatementOrder array{id: int, ticket_number: string, received_at: string|null, department: string|null, delivery_notes: list<int>, subtotal: int, discount: int, tax: int, total: int, paid: int, balance: int, invoice: array{id: int, number: int}|null}
 * @phpstan-type Statement array{company: array{id: int, name: string, national_id: string|null, economic_code: string|null, address: string|null, payment_terms_days: int}, from: string, to: string, previous_balance: int, orders: list<StatementOrder>, departments: list<array{name: string|null, orders: int, total: int}>, delivery_notes: list<array{id: int, number: int, type: string, confirmed_at: string|null, order_id: int|null, items: float, differences: int}>, totals: array{orders: int, subtotal: int, discount: int, tax: int, total: int, paid: int, balance: int}, uninvoiced_order_ids: list<int>}
 */
final class CompanyStatement
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return Statement
     */
    public function build(Company $company, string $from, string $to): array
    {
        $tz = (string) $this->settings->get('general.timezone');
        $start = CarbonImmutable::parse($from, $tz)->startOfDay()->utc();
        $end = CarbonImmutable::parse($to, $tz)->endOfDay()->utc();

        $orders = Order::query()
            ->where('company_id', $company->id)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('received_at', [$start, $end])
            ->with(['department', 'invoices'])
            ->orderBy('received_at')
            ->get();

        $notes = DeliveryNote::query()
            ->where('company_id', $company->id)
            ->where(fn ($query) => $query->whereIn('order_id', $orders->modelKeys())->orWhereBetween('confirmed_at', [$start, $end]))
            ->whereNotNull('confirmed_at')
            ->with('lines')
            ->orderBy('number')
            ->get();

        $rows = array_values($orders->map(fn (Order $order): array => [
            'id' => $order->id,
            'ticket_number' => $order->ticket_number,
            'received_at' => $order->received_at?->toIso8601String(),
            'department' => $order->department?->name,
            'delivery_notes' => array_values($notes->where('order_id', $order->id)->where('type', DeliveryNoteType::In)->pluck('number')->map(fn (mixed $n): int => (int) $n)->all()),
            'subtotal' => $order->subtotal,
            'discount' => $order->discount_amount,
            'tax' => $order->tax_amount,
            'total' => $order->total,
            'paid' => $order->paid_amount,
            'balance' => $order->balance(),
            'invoice' => ($invoice = $order->invoices->first()) === null ? null : ['id' => $invoice->id, 'number' => $invoice->number],
        ])->all());

        $departments = collect($rows)->groupBy('department')->map(fn ($group, $name): array => [
            'name' => $name === '' ? null : (string) $name,
            'orders' => $group->count(),
            'total' => (int) $group->sum('total'),
        ])->values()->all();

        $previous = Order::query()
            ->where('company_id', $company->id)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->where('received_at', '<', $start)
            ->selectRaw('coalesce(sum(greatest(total - paid_amount, 0)), 0) as owed')
            ->value('owed');

        $sum = fn (string $key): int => (int) array_sum(array_column($rows, $key));

        return [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'national_id' => $company->national_id,
                'economic_code' => $company->economic_code,
                'address' => $company->address,
                'payment_terms_days' => $company->payment_terms_days,
            ],
            'from' => $from,
            'to' => $to,
            'previous_balance' => (int) $previous,
            'orders' => $rows,
            'departments' => array_values($departments),
            'delivery_notes' => array_values($notes->map(fn (DeliveryNote $note): array => [
                'id' => $note->id,
                'number' => $note->number,
                'type' => $note->type->value,
                'confirmed_at' => $note->confirmed_at?->toIso8601String(),
                'order_id' => $note->order_id,
                'items' => (float) $note->lines->sum(fn (DeliveryNoteLine $line): float => (float) $line->counted_quantity),
                'differences' => $note->lines->filter(fn (DeliveryNoteLine $line): bool => $line->difference() !== 0.0)->count(),
            ])->all()),
            'totals' => [
                'orders' => count($rows),
                'subtotal' => $sum('subtotal'),
                'discount' => $sum('discount'),
                'tax' => $sum('tax'),
                'total' => $sum('total'),
                'paid' => $sum('paid'),
                'balance' => $sum('balance'),
            ],
            'uninvoiced_order_ids' => array_values(array_map(fn (array $row): int => $row['id'], array_filter($rows, fn (array $row): bool => $row['invoice'] === null))),
        ];
    }
}
