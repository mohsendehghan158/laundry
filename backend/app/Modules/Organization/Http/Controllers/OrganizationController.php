<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Http\Requests\UpdateOrganizationRequest;
use App\Modules\Organization\Http\Resources\OrganizationResource;
use Illuminate\Container\Attributes\CurrentUser;

class OrganizationController
{
    /**
     * Show the current organization
     */
    public function show(#[CurrentUser] User $actor): OrganizationResource
    {
        return new OrganizationResource($actor->organization()->firstOrFail());
    }

    /**
     * Update the current organization
     */
    public function update(UpdateOrganizationRequest $request): OrganizationResource
    {
        $organization = $request->actor()->organization()->firstOrFail();
        $organization->update($request->validated());

        return new OrganizationResource($organization);
    }
}
