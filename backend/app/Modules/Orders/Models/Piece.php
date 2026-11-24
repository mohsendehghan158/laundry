<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Enums\PieceStatus;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $order_id
 * @property int $order_line_id
 * @property string $tag_code
 * @property int $number
 * @property PieceStatus $status
 * @property int|null $color_id
 * @property string|null $color_name
 * @property string|null $brand
 * @property string|null $notes
 * @property Carbon|null $ready_at
 * @property Carbon|null $handed_over_at
 */
class Piece extends Model
{
    use BelongsToOrganization;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PieceStatus::class,
            'ready_at' => 'datetime',
            'handed_over_at' => 'datetime',
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
     * @return BelongsTo<OrderLine, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class, 'order_line_id');
    }

    /**
     * @return HasMany<PieceDefect, $this>
     */
    public function defects(): HasMany
    {
        return $this->hasMany(PieceDefect::class);
    }
}
