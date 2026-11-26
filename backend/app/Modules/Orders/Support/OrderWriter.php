<?php

declare(strict_types=1);

namespace App\Modules\Orders\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\PieceStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderPricer;
use App\Modules\Organization\Support\Settings;
use Illuminate\Validation\ValidationException;

/**
 * Writes a priced order's lines and tagged pieces, and checks the manual
 * discount against the user's rights. Shared by creating and editing.
 *
 * @phpstan-import-type Priced from OrderPricer
 */
final class OrderWriter
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * Staff need orders.discount to give any discount, and without
     * orders.discount_unlimited it may not exceed the configured share of
     * the subtotal.
     *
     * @param  Priced  $priced
     */
    public function authorizeDiscount(User $actor, array $priced): void
    {
        // Promo codes carry their own rules and need no discount right.
        if ($priced['discount_amount'] === 0 || $priced['promo'] !== null) {
            return;
        }

        if (! $actor->can('orders.discount')) {
            throw ValidationException::withMessages(['discount.value' => __('orders.discount_not_allowed')]);
        }

        if ($actor->can('orders.discount_unlimited')) {
            return;
        }

        $max = (float) $this->settings->get('orders.max_discount_percent');

        if ($priced['discount_amount'] * 100 > $priced['subtotal'] * $max) {
            throw ValidationException::withMessages(['discount.value' => __('orders.discount_over_limit', ['max' => $max])]);
        }
    }

    /**
     * The order's money and turnaround columns from a priced request.
     *
     * @param  Priced  $priced
     * @return array<string, mixed>
     */
    public function attributes(array $priced, ?string $discountReason): array
    {
        return [
            'turnaround_tier_id' => $priced['tier']?->id,
            'turnaround_name' => $priced['tier']?->name,
            'surcharge_percent' => $priced['surcharge_percent'],
            'subtotal' => $priced['subtotal'],
            'discount_type' => $priced['discount_type'],
            'discount_value' => $priced['discount_type'] === null ? 0 : $priced['discount_value'],
            'discount_amount' => $priced['discount_amount'],
            'discount_reason' => match (true) {
                $priced['promo'] !== null => __('orders.promo.reason', ['code' => $priced['promo']->code]),
                $priced['discount_type'] === null => null,
                default => $discountReason,
            },
            'promo_code_id' => $priced['promo']?->id,
            'tax_amount' => $priced['tax_amount'],
            'total' => $priced['total'],
        ];
    }

    /**
     * Creates the lines, their upcharges and one tagged piece per physical
     * item. Tags are the ticket number and the piece's position, e.g.
     * T1-04217-03; orders of 100 pieces or more use three digits.
     * Without $tagPieces only the lines are written: an online order's
     * pieces are tagged when they reach the shop.
     *
     * @param  Priced  $priced
     */
    public function writeLines(Order $order, array $priced, bool $tagPieces = true): void
    {
        $pieceCount = array_sum(array_column($priced['lines'], 'piece_count'));
        $width = max(2, strlen((string) $pieceCount));
        $number = 0;

        foreach ($priced['lines'] as $position => $line) {
            $orderLine = $order->lines()->create([
                'item_id' => $line['item_id'],
                'service_type_id' => $line['service_type_id'],
                'item_name' => $line['item_name'],
                'service_name' => $line['service_name'],
                'service_code' => $line['service_code'],
                'unit' => $line['unit'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'base_amount' => $line['base_amount'],
                'upcharge_amount' => $line['upcharge_amount'],
                'surcharge_amount' => $line['surcharge_amount'],
                'line_total' => $line['line_total'],
                'notes' => $line['notes'],
                'position' => $position,
            ]);

            foreach ($line['upcharges'] as $upcharge) {
                $orderLine->upcharges()->create($upcharge);
            }

            for ($i = 0; $tagPieces && $i < $line['piece_count']; $i++) {
                $number++;
                $description = $line['pieces'][$i] ?? null;

                $piece = $orderLine->pieces()->create([
                    'organization_id' => $order->organization_id,
                    'order_id' => $order->id,
                    'branch_id' => $order->branch_id,
                    'tag_code' => $order->ticket_number.'-'.str_pad((string) $number, $width, '0', STR_PAD_LEFT),
                    'number' => $number,
                    'status' => PieceStatus::Received,
                    'color_id' => $description['color_id'] ?? null,
                    'color_name' => $description['color_name'] ?? null,
                    'brand' => $description['brand'] ?? null,
                    'notes' => $description['notes'] ?? null,
                ]);

                foreach ($description['defects'] ?? [] as $defect) {
                    $piece->defects()->create($defect);
                }
            }
        }
    }
}
