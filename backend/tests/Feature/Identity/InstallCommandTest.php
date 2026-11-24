<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\ServiceType;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Organization;

it('sets up an organization, its first branch and an owner who can sign in', function () {
    $this->artisan('laundry:install', [
        '--organization' => 'خشکشویی پاکان',
        '--branch' => 'شعبه مرکزی',
        '--branch-code' => 't1',
        '--name' => 'مدیر',
        '--username' => 'Boss',
        '--password' => 'a-strong-password',
    ])->assertSuccessful();

    $organization = Organization::query()->sole();
    $owner = User::query()->sole();

    expect($organization->name)->toBe('خشکشویی پاکان')
        ->and(Branch::query()->sole()->code)->toBe('T1')
        ->and($owner->username)->toBe('boss')
        ->and($owner->organization_id)->toBe($organization->id)
        ->and($owner->isOwner())->toBeTrue()
        ->and(ServiceType::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->count())->toBe(4);

    $this->postJson('/api/v1/auth/login', ['username' => 'boss', 'password' => 'a-strong-password'])->assertOk();
});

it('refuses to run twice', function () {
    Organization::factory()->create();

    $this->artisan('laundry:install', [
        '--organization' => 'x', '--branch' => 'x', '--name' => 'x', '--username' => 'boss', '--password' => 'a-strong-password',
    ])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('rejects a weak password without creating anything', function () {
    $this->artisan('laundry:install', [
        '--organization' => 'x', '--branch' => 'x', '--name' => 'x', '--username' => 'boss', '--password' => '123',
    ])->assertFailed();

    expect(Organization::query()->count())->toBe(0);
});
