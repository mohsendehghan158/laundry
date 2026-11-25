<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Piece;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use App\Modules\Organization\Models\Branch;
use App\Modules\Production\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $number
 * @property int $from_branch_id
 * @property int $to_branch_id
 * @property TransferStatus $status
 * @property int|null $bags_sent
 * @property int|null $bags_received
 * @property string|null $note
 * @property int|null $created_by
 * @property int|null $sent_by
 * @property Carbon|null $sent_at
 * @property int|null $received_by
 * @property Carbon|null $received_at
 * @property Carbon|null $created_at
 */
class Transfer extends Model
{
    use BelongsToOrganization;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => TransferStatus::class, 'sent_at' => 'datetime', 'received_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id')->withTrashed();
    }

    /**
     * @return BelongsToMany<Piece, $this>
     */
    public function pieces(): BelongsToMany
    {
        return $this->belongsToMany(Piece::class, 'transfer_pieces')->withPivot('received_at')->orderBy('pieces.tag_code');
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
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by')->withTrashed();
    }
}
