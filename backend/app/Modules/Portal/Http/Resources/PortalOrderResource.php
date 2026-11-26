<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Resources;

use App\Modules\Billing\Enums\PaymentKind;
use App\Modules\Billing\Enums\PaymentMethod;
use App\Modules\Billing\Models\Payment;
use App\Modules\Catalog\Enums\PriceUnit;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Orders\Models\OrderStatusChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order as its customer sees it: no staff names, notes or internals.
 *
 * @mixin Order
 */
class PortalOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** Used in the order's address: /orders/{public_id}. */
            'public_id' => $this->public_id,
            'ticket_number' => $this->ticket_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'branch_name' => $this->branch->name,
            'source' => $this->source,
            /** @var array{starts_at: string, ends_at: string, address: string}|null The booked pickup, for online orders. */
            'pickup' => $this->pickup_starts_at === null ? null : [
                'starts_at' => $this->pickup_starts_at->toIso8601String(),
                'ends_at' => $this->pickup_ends_at?->toIso8601String() ?? $this->pickup_starts_at->toIso8601String(),
                'address' => (string) $this->pickup_address,
            ],
            /** The driver lists the items at pickup; until then there are no lines. */
            'items_by_driver' => $this->items_by_driver,
            /** Empty until the order reaches the shop. */
            'received_at' => $this->received_at,
            'due_at' => $this->due_at,
            'ready_at' => $this->ready_at,
            'completed_at' => $this->completed_at,
            'total' => $this->total,
            'paid_amount' => $this->paid_amount,
            'balance' => $this->balance(),
            'piece_count' => (int) ($this->pieces_count ?? 0),
            /** @var list<array{item_name: string, service_name: string, quantity: string, unit: PriceUnit, line_total: int}> */
            'lines' => $this->whenLoaded('lines', fn (): array => $this->lines->map(fn (OrderLine $line): array => [
                'item_name' => $line->item_name,
                'service_name' => $line->service_name,
                'quantity' => (string) $line->quantity,
                'unit' => $line->unit,
                'line_total' => $line->line_total,
            ])->values()->all()),
            /** @var list<array{status: OrderStatus, at: string}> */
            'timeline' => $this->whenLoaded('history', fn (): array => $this->history->map(fn (OrderStatusChange $change): array => [
                'status' => $change->to_status,
                'at' => $change->created_at->toIso8601String(),
            ])->values()->all()),
            /** @var list<array{method: PaymentMethod, kind: PaymentKind, amount: int, at: string}> */
            'payments' => $this->whenLoaded('payments', fn (): array => $this->payments->map(fn (Payment $payment): array => [
                'method' => $payment->method,
                'kind' => $payment->kind,
                'amount' => $payment->amount,
                'at' => $payment->created_at->toIso8601String(),
            ])->values()->all()),
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            /** Part of the total. */
            'delivery_fee' => $this->delivery_fee,
            /** The customer may still call it off (POST …/cancel). */
            'can_cancel' => $this->status === OrderStatus::PendingPickup,
        ];
    }
}
