<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Enums\PieceStatus;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use App\Modules\Organization\Models\Branch;
use App\Modules\Production\Enums\PieceFlag;
use App\Modules\Production\Models\PieceScan;
use App\Modules\Production\Models\QcInspection;
use App\Modules\Production\Models\Station;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $order_id
 * @property int $order_line_id
 * @property int|null $branch_id where the piece physically is
 * @property int|null $station_id the station that last scanned it
 * @property string $tag_code
 * @property int $number
 * @property PieceStatus $status
 * @property PieceFlag|null $flag
 * @property string|null $flag_note
 * @property Carbon|null $last_scanned_at
 * @property Carbon|null $assembled_at
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
            'flag' => PieceFlag::class,
            'last_scanned_at' => 'datetime',
            'assembled_at' => 'datetime',
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

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Station, $this>
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class)->withTrashed();
    }

    /**
     * The piece's history, oldest first.
     *
     * @return HasMany<PieceScan, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PieceScan::class)->orderBy('id');
    }

    /**
     * @return HasMany<QcInspection, $this>
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(QcInspection::class)->orderBy('id');
    }

    /** Can be worked on or gathered now (not held, away or lost). */
    public function isAvailable(): bool
    {
        return $this->flag === null || ! $this->flag->blocksWork();
    }
}
