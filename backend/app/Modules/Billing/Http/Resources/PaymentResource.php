<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            /** Present in a shift's payment list. */
            'ticket_number' => $this->whenLoaded('order', fn (): string => $this->order->ticket_number),
            'kind' => $this->kind,
            'method' => $this->method,
            /** Whole rials, always positive; `kind` says which way it went. */
            'amount' => $this->amount,
            'reference' => $this->reference,
            'notes' => $this->notes,
            /** @var array{id: int, name: string}|null */
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : ['id' => $this->user->id, 'name' => $this->user->name]),
            'created_at' => $this->created_at,
        ];
    }
}
