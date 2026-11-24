<?php

declare(strict_types=1);

namespace App\Modules\Organization\Tenancy;

use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * The organization the current unit of work belongs to.
 *
 * During an HTTP request it is derived from the authenticated user, so
 * no middleware has to remember to set it. Console commands and queued
 * jobs have no user and must wrap their work in runFor().
 */
final class OrganizationContext
{
    private ?int $explicit = null;

    public function id(): ?int
    {
        if ($this->explicit !== null) {
            return $this->explicit;
        }

        // hasUser() never triggers user resolution, which matters because
        // the user query itself passes through the organization scope.
        if (! Auth::hasUser()) {
            return null;
        }

        $organizationId = Auth::user()?->getAttribute('organization_id');

        return is_int($organizationId) ? $organizationId : null;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runFor(int $organizationId, Closure $callback): mixed
    {
        $previous = $this->explicit;
        $this->explicit = $organizationId;

        try {
            return $callback();
        } finally {
            $this->explicit = $previous;
        }
    }
}
