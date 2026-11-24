<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Resources\AuthUserResource;
use App\Modules\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthController
{
    /**
     * Sign in
     *
     * Starts a cookie session. Call `GET /sanctum/csrf-cookie` first and send
     * the `XSRF-TOKEN` cookie value back in the `X-XSRF-TOKEN` header.
     * Wrong credentials and inactive accounts fail with 422 on `username`;
     * after five failed attempts the response is 429.
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): AuthUserResource
    {
        $user = $request->authenticate();

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        activity('auth')
            ->causedBy($user)
            ->event('login')
            ->withProperties(['ip' => $request->ip()])
            ->log('login');

        return new AuthUserResource($user);
    }

    /**
     * Sign out
     */
    public function logout(Request $request, #[CurrentUser] User $actor): Response
    {
        activity('auth')
            ->causedBy($actor)
            ->event('logout')
            ->log('logout');

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
