<?php

declare(strict_types=1);

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
    ->withMiddleware(function (Middleware $middleware): void {
        // The API is only reachable through the reverse proxy in front of it.
        $middleware->trustProxies(at: '*');

        // Cookie sessions for the first-party SPA (Sanctum).
        $middleware->statefulApi();
        $middleware->throttleApi();
        $middleware->api(prepend: [SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(new ApiExceptionRenderer);
    })->create();
