<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Requests\UpdatePasswordRequest;
use App\Modules\Identity\Http\Resources\AuthUserResource;
use App\Modules\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

class MeController
{
    /**
     * Show the signed-in user
     *
     * Includes the permissions and branches the interface is built from.
     */
    public function show(#[CurrentUser] User $actor): AuthUserResource
    {
        return new AuthUserResource($actor);
    }

    /**
     * Change own password
     */
    public function updatePassword(UpdatePasswordRequest $request): Response
    {
        $request->actor()->update(['password' => $request->validated('password')]);

        return response()->noContent();
    }
}
