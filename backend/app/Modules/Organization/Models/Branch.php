<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use App\Modules\Organization\Database\Factories\BranchFactory;
use App\Modules\Organization\Enums\BranchType;
use App\Modules\Organization\Policies\BranchPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $code
 * @property BranchType $type
 * @property string|null $phone
 * @property string|null $address
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'code', 'type', 'phone', 'address', 'is_active'])]
#[UseFactory(BranchFactory::class)]
#[UsePolicy(BranchPolicy::class)]
class Branch extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    use LogsActivity, SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BranchType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Limits the query to branches the user may work with.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->hasAllBranchAccess()) {
            return;
        }

        $query->whereHas('users', fn (Builder $users) => $users->whereKey($user->getKey()));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('branch')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
