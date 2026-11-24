<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Concerns;

use App\Modules\Organization\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Shared behaviour of catalog records: organization scoping, display
 * order, an active flag and audit logging under "catalog".
 *
 * @mixin Model
 */
trait CatalogEntry
{
    use BelongsToOrganization, LogsActivity;

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalog')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
