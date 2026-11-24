<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Models\User;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends ApiFormRequest
{
    private const int MAX_ATTEMPTS = 5;

    protected array $latinDigits = ['username'];

    protected array $lowercase = ['username'];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            /** Keep the session alive after the browser closes. */
            'remember' => ['boolean'],
        ];
    }

    /**
     * Signs the user in or fails with a validation error on "username".
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('username', 'password');

        if (! Auth::guard('web')->attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['username' => __('auth.failed')]);
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if (! $user->is_active) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages(['username' => __('auth.inactive')]);
        }

        RateLimiter::clear($this->throttleKey());

        // The session belongs to the "web" guard; the rest of this request
        // (organization scoping, audit log) asks the default guard.
        Auth::setUser($user);

        return $user;
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        throw ValidationException::withMessages([
            'username' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($this->throttleKey())]),
        ])->status(429);
    }

    private function throttleKey(): string
    {
        return 'login:'.$this->string('username').'|'.$this->ip();
    }
}
