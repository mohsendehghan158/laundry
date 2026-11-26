<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Modules\Organization\Concerns\BelongsToOrganization;
use App\Modules\Organization\Models\Branch;
use App\Modules\Production\Database\Factories\StationFactory;
use App\Modules\Production\Enums\StationKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A step of a branch's workshop where pieces are scanned.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $branch_id
 * @property string $name
 * @property string $code
 * @property StationKind $kind
 * @property int $sort_order
 * @property bool $is_active
 * @property-read Branch|null $branch archived branches are not loaded
 */
#[Fillable(['branch_id', 'name', 'code', 'kind', 'sort_order', 'is_active'])]
#[UseFactory(StationFactory::class)]
class Station extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<StationFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'kind' => 'process',
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => StationKind::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
