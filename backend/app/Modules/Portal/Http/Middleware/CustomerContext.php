<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Middleware;

use App\Modules\Customers\Models\Customer;
use App\Modules\Organization\Tenancy\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * After auth:customer: runs the request inside the signed-in customer's
 * organization.
 */
final class CustomerContext
{
    public function __construct(private readonly OrganizationContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer instanceof Customer, 401);

        return $this->context->runFor($customer->organization_id, fn (): Response => $next($request));
    }
}
