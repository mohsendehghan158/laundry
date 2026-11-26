<?php

declare(strict_types=1);

namespace App\Modules\Organization\Concerns;

use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Tenancy\OrganizationContext;
use App\Modules\Organization\Tenancy\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keeps a model inside its organization: reads are filtered by the
 * current organization and new rows are stamped with it.
 *
 * @property-read Organization $organization
 *
 * @mixin Model
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('organization_id') === null) {
                $model->setAttribute('organization_id', app(OrganizationContext::class)->id());
            }
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
