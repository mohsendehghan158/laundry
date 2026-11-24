<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Modules\Organization\Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 */
#[Fillable(['name', 'slug', 'is_active'])]
#[UseFactory(OrganizationFactory::class)]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('organization')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
