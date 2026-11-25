<?php

declare(strict_types=1);

namespace App\Modules\Portal\Support;

use App\Modules\Organization\Models\Organization;

/**
 * The organization the customer portal belongs to. Version 1 runs one
 * organization per installation: the one named by PORTAL_ORGANIZATION
 * (its slug), or the first one.
 */
final class Portal
{
    private ?int $resolved = null;

    public function organizationId(): int
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $slug = config('services.portal.organization');
        $query = Organization::query();
        $organization = is_string($slug) && $slug !== '' ? $query->where('slug', $slug)->first() : $query->orderBy('id')->first();
        abort_if($organization === null, 503, 'The portal has no organization.');

        return $this->resolved = $organization->id;
    }
}
