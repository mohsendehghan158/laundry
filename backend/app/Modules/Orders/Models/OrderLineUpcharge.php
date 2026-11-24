<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Catalog\Enums\UpchargeType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $order_line_id
 * @property int|null $upcharge_id
 * @property string $name
 * @property UpchargeType $type
 * @property int $value
 * @property int $amount
 */
class OrderLineUpcharge extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => UpchargeType::class, 'value' => 'integer', 'amount' => 'integer'];
    }
}
