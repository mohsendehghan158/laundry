<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Catalog\Enums\PriceUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $item_id
 * @property int|null $service_type_id
 * @property string $item_name
 * @property string $service_name
 * @property string $service_code
 * @property PriceUnit $unit
 * @property string $quantity
 * @property int $unit_price
 * @property int $base_amount
 * @property int $upcharge_amount
 * @property int $surcharge_amount
 * @property int $line_total
 * @property string|null $notes
 * @property int $position
 * @property-read Order $order
 */
class OrderLine extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => PriceUnit::class,
            'quantity' => 'decimal:3',
            'unit_price' => 'integer',
            'base_amount' => 'integer',
            'upcharge_amount' => 'integer',
            'surcharge_amount' => 'integer',
            'line_total' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<OrderLineUpcharge, $this>
     */
    public function upcharges(): HasMany
    {
        return $this->hasMany(OrderLineUpcharge::class);
    }

    /**
     * @return HasMany<Piece, $this>
     */
    public function pieces(): HasMany
    {
        return $this->hasMany(Piece::class)->orderBy('number');
    }
}
