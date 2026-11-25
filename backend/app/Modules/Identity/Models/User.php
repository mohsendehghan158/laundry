<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\UserFactory;
use App\Modules\Identity\Policies\UserPolicy;
use App\Modules\Identity\Support\PermissionRegistry;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use App\Modules\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\CausesActivity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

/**
 * A staff member. Customers are a separate model with their own guard.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $username
 * @property string|null $email
 * @property string|null $mobile
 * @property string $password
 * @property bool $is_active
 * @property string $locale
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'username', 'email', 'mobile', 'password', 'is_active', 'locale'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
#[UsePolicy(UserPolicy::class)]
class User extends Authenticatable
{
    use BelongsToOrganization, CausesActivity;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles, LogsActivity, SoftDeletes;

    /** The session guard staff sign in with; roles and permissions belong to it. */
    public const string GUARD = 'web';

    /**
     * Read by spatie/laravel-permission. Without it, roles would be looked up
     * under whatever guard the request last switched to, which in a customer
     * portal request is "customer".
     */
    protected string $guard_name = self::GUARD;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
        'locale' => 'fa',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Branches the user is assigned to.
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    public function isOwner(): bool
    {
        return $this->hasRole(PermissionRegistry::OWNER);
    }

    public function hasAllBranchAccess(): bool
    {
        return $this->can('branches.access_all');
    }

    public function canAccessBranch(Branch $branch): bool
    {
        return $this->hasAllBranchAccess()
            || $this->branches()->whereKey($branch->getKey())->exists();
    }

    /**
     * Every permission the user effectively holds.
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        if ($this->isOwner()) {
            return PermissionRegistry::all();
        }

        return array_values(
            $this->getAllPermissions()
                ->map(fn (Permission $permission): string => $permission->name)
                ->sort()
                ->all(),
        );
    }

    /**
     * Limits the query to users the actor may see: everyone for those
     * with access to all branches, otherwise colleagues who share a branch.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, self $actor): void
    {
        if ($actor->hasAllBranchAccess()) {
            return;
        }

        $branchIds = $actor->branches()->pluck('branches.id');

        $query->where(fn (Builder $q) => $q
            ->whereKey($actor->getKey())
            ->orWhereHas('branches', fn (Builder $branches) => $branches->whereKey($branchIds)));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('user')
            ->logOnly(['name', 'username', 'email', 'mobile', 'is_active', 'locale'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
