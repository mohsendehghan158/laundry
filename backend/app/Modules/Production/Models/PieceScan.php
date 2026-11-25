<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Piece;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use App\Modules\Organization\Models\Branch;
use App\Modules\Production\Enums\PieceEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One event in a piece's history. Append-only.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $piece_id
 * @property int $order_id
 * @property PieceEvent $event
 * @property int|null $station_id
 * @property int|null $branch_id
 * @property int|null $user_id
 * @property string|null $note
 * @property Carbon $created_at
 */
class PieceScan extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['event' => PieceEvent::class, 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Piece, $this>
     */
    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    /**
     * @return BelongsTo<Station, $this>
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
