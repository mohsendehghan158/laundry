<?php

declare(strict_types=1);

namespace Tests;

use App\Modules\Identity\Actions\SyncPermissions;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\PermissionRegistry;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Organization;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The SPA always states its interface language; without this the
        // test client's built-in "en-us" header would select English.
        $this->withHeader('Accept-Language', 'fa');

        // Feature tests start with the built-in roles in place; tests that
        // never touch the database skip this.
        if (Schema::hasTable('permissions')) {
            app(SyncPermissions::class)->handle();
        }
    }

    /**
     * A user holding the given role, in a new organization unless one is given.
     *
     * @param  list<Branch>  $branches  branches the user is assigned to
     */
    protected function userWithRole(string $role, ?Organization $organization = null, array $branches = []): User
    {
        $user = User::factory()
            ->for($organization ?? Organization::factory()->create())
            ->withRoles($role)
            ->create();

        $user->branches()->sync(array_map(fn (Branch $branch): int => $branch->id, $branches));

        return $user;
    }

    protected function owner(?Organization $organization = null): User
    {
        return $this->userWithRole(PermissionRegistry::OWNER, $organization);
    }

    /**
     * Signs in as a new owner and returns them.
     */
    protected function actingAsOwner(?Organization $organization = null): User
    {
        $owner = $this->owner($organization);

        $this->actingAs($owner);

        return $owner;
    }
}
