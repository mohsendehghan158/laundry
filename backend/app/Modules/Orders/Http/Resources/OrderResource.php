<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Resources;

use App\Modules\Billing\Models\Payment;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderStatusChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order with everything about it. Lists use the same shape without
 * lines and history. Amounts are whole rials.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /** Opaque id for the public tracking link. */
            'public_id' => $this->public_id,
            'ticket_number' => $this->ticket_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            /** @var array{id: int, name: string, code: string} */
            'branch' => ['id' => $this->branch->id, 'name' => $this->branch->name, 'code' => $this->branch->code],
            /** @var array{id: int, name: string, mobile: string, alert: string|null} */
            'customer' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'mobile' => $this->customer->mobile,
                'alert' => $this->customer->alert,
            ],
            /** @var array{id: int|null, name: string|null, surcharge_percent: int} */
            'turnaround' => ['id' => $this->turnaround_tier_id, 'name' => $this->turnaround_name, 'surcharge_percent' => $this->surcharge_percent],
            'received_at' => $this->received_at,
            'due_at' => $this->due_at,
            'ready_at' => $this->ready_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'cancel_reason' => $this->cancel_reason,
            'rack_location' => $this->rack_location,
            'notes' => $this->notes,
            'subtotal' => $this->subtotal,
            /** @var array{type: 'amount'|'percent', value: float, amount: int, reason: string|null}|null */
            'discount' => $this->discount_type === null ? null : [
                'type' => $this->discount_type->value,
                'value' => (float) $this->discount_value,
                'amount' => $this->discount_amount,
                'reason' => $this->discount_reason,
            ],
            /** @var array{id: int, code: string}|null The promo code the discount came from. */
            'promo_code' => $this->whenLoaded('promoCode', fn (): ?array => $this->promoCode === null ? null : ['id' => $this->promoCode->id, 'code' => $this->promoCode->code]),
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'paid_amount' => $this->paid_amount,
            /** Still to be paid. */
            'balance' => $this->balance(),
            'piece_count' => $this->whenCounted('pieces'),
            /** Send back with an edit; a newer version on the server means someone else changed the order. */
            'version' => $this->version,
            /** @var list<OrderStatus> Statuses this order can be moved to with POST /orders/{id}/transitions. */
            'available_transitions' => $this->status->transitions(),
            'created_at' => $this->created_at,
            /** @var array{id: int, name: string}|null */
            'created_by' => $this->whenLoaded('creator', fn (): ?array => $this->creator === null ? null : ['id' => $this->creator->id, 'name' => $this->creator->name]),
            'lines' => OrderLineResource::collection($this->whenLoaded('lines')),
            /** @var list<array{id: int, kind: 'payment'|'refund', method: 'cash'|'card'|'transfer', amount: int, reference: string|null, notes: string|null, user: array{id: int, name: string}|null, created_at: string}> */
            'payments' => $this->whenLoaded('payments', fn (): array => $this->payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'kind' => $payment->kind->value,
                'method' => $payment->method->value,
                'amount' => $payment->amount,
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'user' => $payment->user === null ? null : ['id' => $payment->user->id, 'name' => $payment->user->name],
                'created_at' => $payment->created_at->toIso8601String(),
            ])->all()),
            /** @var list<array{from_status: OrderStatus|null, to_status: OrderStatus, user: array{id: int, name: string}|null, reason: string|null, created_at: string}> */
            'history' => $this->whenLoaded('history', fn (): array => $this->history->map(fn (OrderStatusChange $change): array => [
                'from_status' => $change->from_status,
                'to_status' => $change->to_status,
                'user' => $change->user === null ? null : ['id' => $change->user->id, 'name' => $change->user->name],
                'reason' => $change->reason,
                'created_at' => $change->created_at->toIso8601String(),
            ])->all()),
        ];
    }
}
