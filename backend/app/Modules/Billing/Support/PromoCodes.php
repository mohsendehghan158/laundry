<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\PromoCode;
use App\Modules\Orders\Enums\DiscountType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Support\Text\Digits;
use Illuminate\Validation\ValidationException;

/**
 * Checks a promo code against an order and works out its discount.
 * Usage is counted from orders that carry the code and are not cancelled.
 */
final class PromoCodes
{
    /**
     * @return array{promo: PromoCode, amount: int} the code and its discount in rials
     */
    public function apply(string $code, int $subtotal, ?int $customerId, ?int $ignoreOrderId = null): array
    {
        $promo = PromoCode::query()->where('code', self::normalize($code))->first();

        if ($promo === null || ! $promo->is_active) {
            $this->fail('not_found');
        }

        if (($promo->starts_at !== null && $promo->starts_at->isFuture()) || ($promo->ends_at !== null && $promo->ends_at->isPast())) {
            $this->fail('expired');
        }

        if ($subtotal < $promo->min_order) {
            $this->fail('min_order', ['min' => number_format($promo->min_order)]);
        }

        $uses = Order::query()
            ->where('promo_code_id', $promo->id)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->when($ignoreOrderId, fn ($query) => $query->whereKeyNot($ignoreOrderId));

        if ($promo->usage_limit !== null && (clone $uses)->count() >= $promo->usage_limit) {
            $this->fail('used_up');
        }

        if ($customerId !== null) {
            if ($promo->per_customer_limit !== null && (clone $uses)->where('customer_id', $customerId)->count() >= $promo->per_customer_limit) {
                $this->fail('customer_limit');
            }

            $previousOrders = Order::query()
                ->where('customer_id', $customerId)
                ->where('status', '!=', OrderStatus::Cancelled)
                ->when($ignoreOrderId, fn ($query) => $query->whereKeyNot($ignoreOrderId));

            if ($promo->first_order_only && $previousOrders->exists()) {
                $this->fail('first_order_only');
            }
        }

        $amount = match ($promo->type) {
            DiscountType::Amount => $promo->value,
            DiscountType::Percent => intdiv($subtotal * $promo->value * 2 + 10_000, 20_000),
        };

        if ($promo->max_discount !== null) {
            $amount = min($amount, $promo->max_discount);
        }

        return ['promo' => $promo, 'amount' => min($amount, $subtotal)];
    }

    /** Codes are matched without regard to case or Persian digits. */
    public static function normalize(string $code): string
    {
        return mb_strtoupper(trim(Digits::toLatin($code)));
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function fail(string $reason, array $replace = []): never
    {
        throw ValidationException::withMessages(['promo_code' => __("orders.promo.{$reason}", $replace)]);
    }
}
