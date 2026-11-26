<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Modules\Organization\Concerns\BelongsToOrganization;
use App\Modules\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A rack where finished orders wait for pickup, divided into slots.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $branch_id
 * @property string $name
 * @property string $code
 * @property int $sort_order
 * @property bool $is_active
 * @property-read Branch|null $branch archived branches are not loaded
 */
#[Fillable(['branch_id', 'name', 'code', 'sort_order', 'is_active'])]
class Rack extends Model
{
    use BelongsToOrganization;
    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<RackSlot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(RackSlot::class)->orderBy('position');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /** "A" + 7 → "A-07" */
    public function slotCode(int $position): string
    {
        return $this->code.'-'.str_pad((string) $position, 2, '0', STR_PAD_LEFT);
    }
}
