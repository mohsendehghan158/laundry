<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Piece;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A photo of a piece on the private disk.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $piece_id
 * @property string $disk
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property int|null $uploaded_by
 * @property Carbon $created_at
 * @property-read Piece $piece
 * @property-read User|null $uploader
 */
class Media extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $table = 'media';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'size' => 'integer'];
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
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }
}
