<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of an invoice, whole rials: amount − discount + tax = total.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int|null $order_id
 * @property int $position
 * @property string $description
 * @property string $quantity
 * @property string|null $unit
 * @property int $unit_price
 * @property int $amount
 * @property int $discount
 * @property int $tax
 * @property int $total
 * @property-read Order|null $order
 */
class InvoiceLine extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'unit_price' => 'integer',
            'amount' => 'integer',
            'discount' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
