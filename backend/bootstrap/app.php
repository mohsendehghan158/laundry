<?php

declare(strict_types=1);

use App\Modules\Portal\Http\Middleware\CustomerContext;
use App\Modules\Portal\Http\Middleware\PortalContext;
use App\Support\Http\ApiExceptionRenderer;
use App\Support\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Private socket channels (production boards), authorised with the SPA session.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['prefix' => 'api/v1', 'middleware' => ['api', 'auth:sanctum']])
    ->withMiddleware(function (Middleware $middleware): void {
        // The API is only reachable through the reverse proxy in front of it.
        $middleware->trustProxies(at: '*');

        // Cookie sessions for the first-party SPA (Sanctum).
        $middleware->statefulApi();
        $middleware->throttleApi();
        $middleware->api(prepend: [SetLocale::class]);
        $middleware->alias([
            'portal' => PortalContext::class,
            'customer.context' => CustomerContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(new ApiExceptionRenderer);
    })->create();
