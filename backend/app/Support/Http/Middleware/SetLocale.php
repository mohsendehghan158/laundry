<?php

declare(strict_types=1);

namespace App\Support\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the response language from the Accept-Language header, falling
 * back to the application default (Persian).
 */
final class SetLocale
{
    /** @var list<string> */
    public const array SUPPORTED = ['fa', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage(self::SUPPORTED);

        if ($request->headers->has('Accept-Language') && $locale !== null) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
