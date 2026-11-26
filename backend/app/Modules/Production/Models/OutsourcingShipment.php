<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Piece;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use App\Modules\Organization\Models\Branch;
use App\Modules\Production\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $number
 * @property int $vendor_id
 * @property int $branch_id
 * @property ShipmentStatus $status
 * @property Carbon|null $expected_at
 * @property int|null $cost
 * @property string|null $note
 * @property int|null $sent_by
 * @property Carbon $sent_at
 * @property Carbon|null $closed_at
 * @property-read Vendor $vendor
 * @property-read Branch $branch
 * @property-read User|null $sender
 */
class OutsourcingShipment extends Model
{
    use BelongsToOrganization;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => ShipmentStatus::class, 'expected_at' => 'datetime', 'sent_at' => 'datetime', 'closed_at' => 'datetime', 'cost' => 'integer'];
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return BelongsToMany<Piece, $this>
     */
    public function pieces(): BelongsToMany
    {
        return $this->belongsToMany(Piece::class, 'outsourcing_shipment_pieces')->withPivot('returned_at')->orderBy('pieces.tag_code');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by')->withTrashed();
    }
}
