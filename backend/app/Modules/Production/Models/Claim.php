<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Piece;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use App\Modules\Production\Enums\ClaimKind;
use App\Modules\Production\Enums\ClaimStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $number
 * @property int $order_id
 * @property int $piece_id
 * @property ClaimKind $kind
 * @property ClaimStatus $status
 * @property string $description
 * @property string|null $resolution
 * @property int|null $compensation
 * @property int|null $created_by
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property-read Order $order
 * @property-read Piece $piece
 * @property-read User|null $creator
 * @property-read User|null $resolver
 */
class Claim extends Model
{
    use BelongsToOrganization;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => ClaimKind::class, 'status' => ClaimStatus::class, 'resolved_at' => 'datetime', 'compensation' => 'integer'];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Piece, $this>
     */
    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by')->withTrashed();
    }
}
