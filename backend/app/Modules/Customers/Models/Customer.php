<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use App\Modules\Customers\Database\Factories\CustomerFactory;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use App\Support\Text\Mobile;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $mobile
 * @property string|null $phone
 * @property string|null $notes
 * @property string|null $alert
 * @property int|null $created_by
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'mobile', 'phone', 'notes', 'alert'])]
#[UseFactory(CustomerFactory::class)]
class Customer extends Model implements AuthenticatableContract
{
    /** Customers sign in to the portal with a texted code; they have no password. */
    use Authenticatable;

    use BelongsToOrganization;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['remember_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_login_at' => 'datetime'];
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->orderByDesc('is_default')->orderBy('id');
    }

    /**
     * Matches part of a name or mobile number, typed with any digits.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $term = trim($term);
        $digits = Mobile::searchable($term);

        $query->where(function (Builder $query) use ($term, $digits): void {
            $query->whereLike('name', "%{$term}%");

            // Enough digits to mean a phone number rather than a stray character.
            if (strlen($digits) >= 3) {
                $query->orWhereLike('mobile', "%{$digits}%")->orWhereLike('phone', "%{$digits}%");
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('customer')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
