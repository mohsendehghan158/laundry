<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An invoice; lists leave out the lines and orders. Amounts are whole rials.
 *
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'issued_at' => $this->issued_at,
            /** @var array{id: int, name: string} */
            'branch' => ['id' => $this->branch->id, 'name' => $this->branch->name],
            /** @var array{id: int, name: string, mobile: string} */
            'customer' => ['id' => $this->customer->id, 'name' => $this->customer->name, 'mobile' => $this->customer->mobile],
            /** @var array{name: string, national_id: string|null, economic_code: string|null, registration_number: string|null, postal_code: string|null, address: string|null, phone: string|null} */
            'seller' => $this->seller,
            /** @var array{type: 'individual'|'legal', name: string, national_id: string|null, economic_code: string|null, registration_number: string|null, postal_code: string|null, address: string|null, phone: string|null} */
            'buyer' => $this->buyer,
            'vat_percent' => (float) $this->vat_percent,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'notes' => $this->notes,
            /** @var array{id: int, name: string}|null */
            'created_by' => $this->whenLoaded('creator', fn (): ?array => $this->creator === null ? null : ['id' => $this->creator->id, 'name' => $this->creator->name]),
            /** @var array{at: string, reason: string, by: array{id: int, name: string}|null}|null Set once voided. */
            'void' => $this->voided_at === null ? null : [
                'at' => $this->voided_at->toIso8601String(),
                'reason' => (string) $this->void_reason,
                'by' => $this->voider === null ? null : ['id' => $this->voider->id, 'name' => $this->voider->name],
            ],
            /** @var list<array{id: int, ticket_number: string}> */
            'orders' => $this->whenLoaded('orders', fn (): array => $this->orders->map(fn (Order $order): array => ['id' => $order->id, 'ticket_number' => $order->ticket_number])->all()),
            /** @var list<array{position: int, order_id: int|null, description: string, quantity: float, unit: string|null, unit_price: int, amount: int, discount: int, tax: int, total: int}> */
            'lines' => $this->whenLoaded('lines', fn (): array => $this->lines->map(fn (InvoiceLine $line): array => [
                'position' => $line->position,
                'order_id' => $line->order_id,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit' => $line->unit,
                'unit_price' => $line->unit_price,
                'amount' => $line->amount,
                'discount' => $line->discount,
                'tax' => $line->tax,
                'total' => $line->total,
            ])->all()),
        ];
    }
}
