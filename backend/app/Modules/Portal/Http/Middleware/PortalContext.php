<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Middleware;

use App\Modules\Organization\Tenancy\OrganizationContext;
use App\Modules\Portal\Support\Portal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs a public portal request (sign-in, public pages) inside the portal's
 * organization, so every scoped query stays in it.
 */
final class PortalContext
{
    public function __construct(private readonly Portal $portal, private readonly OrganizationContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $this->context->runFor($this->portal->organizationId(), fn (): Response => $next($request));
    }
}
