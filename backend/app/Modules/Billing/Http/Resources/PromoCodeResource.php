<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Models\PromoCode;
use App\Modules\Orders\Enums\DiscountType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PromoCode
 */
class PromoCodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'type' => $this->type,
            /** @var float Rials for "amount", percent for "percent". */
            'value' => $this->type === DiscountType::Percent ? $this->value / 100 : $this->value,
            'max_discount' => $this->max_discount,
            'min_order' => $this->min_order,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'usage_limit' => $this->usage_limit,
            'per_customer_limit' => $this->per_customer_limit,
            'first_order_only' => $this->first_order_only,
            'is_active' => $this->is_active,
            /** Orders (not cancelled) that used the code. */
            'used_count' => $this->whenHas('used_count', fn () => (int) $this->getAttribute('used_count')),
        ];
    }
}
