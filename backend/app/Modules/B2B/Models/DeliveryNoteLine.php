<?php

declare(strict_types=1);

namespace App\Modules\B2B\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $delivery_note_id
 * @property int|null $item_id
 * @property int|null $service_type_id
 * @property int|null $order_line_id
 * @property string $item_name
 * @property string|null $service_name
 * @property string $expected_quantity
 * @property string $counted_quantity
 * @property int $position
 */
class DeliveryNoteLine extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expected_quantity' => 'decimal:3', 'counted_quantity' => 'decimal:3', 'position' => 'integer'];
    }

    /** Counted minus expected: negative when something is missing. */
    public function difference(): float
    {
        return round((float) $this->counted_quantity - (float) $this->expected_quantity, 3);
    }
}
