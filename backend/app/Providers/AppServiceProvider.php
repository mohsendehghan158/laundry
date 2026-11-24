<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Identity\Console\InstallCommand;
use App\Modules\Identity\Console\SyncPermissionsCommand;
use App\Modules\Identity\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->commands([InstallCommand::class, SyncPermissionsCommand::class]);

        // The owner holds every permission, including ones added later,
        // so an organization can never lock itself out by editing roles.
        Gate::before(fn (User $user): ?bool => $user->isOwner() ? true : null);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // Per-username lockout lives in LoginRequest; this caps one address
        // trying many usernames.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by((string) $request->ip()));

        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
            $openApi->secure(SecurityScheme::apiKey('cookie', 'laundry_session'));
        });
    }
}
