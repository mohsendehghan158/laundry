<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Modules\Orders\Models\Piece;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $rack_id
 * @property int $position
 * @property string $code
 */
class RackSlot extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Rack, $this>
     */
    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class)->withTrashed();
    }

    /**
     * @return HasMany<Piece, $this>
     */
    public function pieces(): HasMany
    {
        return $this->hasMany(Piece::class);
    }
}
