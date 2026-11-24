<?php

declare(strict_types=1);

use App\Modules\Audit\Models\Activity;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Organization;
use Illuminate\Support\Facades\Hash;

it('lists users with their roles and branches', function () {
    $owner = $this->actingAsOwner();
    $branch = Branch::factory()->for($owner->organization)->create(['code' => 'T1']);
    $cashier = $this->userWithRole('cashier', $owner->organization, [$branch]);
    $cashier->update(['name' => 'علی رضایی', 'mobile' => '09121234567']);
    $this->userWithRole('driver', $owner->organization)->update(['is_active' => false]);

    $this->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonMissingPath('data.0.password');

    $this->getJson('/api/v1/users?filter[q]=رضایی')
        ->assertJsonPath('data.*.id', [$cashier->id])
        ->assertJsonPath('data.0.roles', ['cashier'])
        ->assertJsonPath('data.0.branches.0.code', 'T1');

    $this->getJson('/api/v1/users?filter[q]=0912123')->assertJsonPath('data.*.id', [$cashier->id]);
    $this->getJson('/api/v1/users?filter[role]=cashier')->assertJsonPath('data.*.id', [$cashier->id]);
    $this->getJson("/api/v1/users?filter[branch_id]={$branch->id}")->assertJsonPath('data.*.id', [$cashier->id]);
    $this->getJson('/api/v1/users?filter[is_active]=false')->assertJsonPath('meta.total', 1);
});

it('never exposes users of another organization', function () {
    $this->actingAsOwner();
    $foreign = User::factory()->create();

    $this->getJson('/api/v1/users')->assertJsonPath('meta.total', 1);
    $this->getJson("/api/v1/users/{$foreign->id}")->assertNotFound();
    $this->putJson("/api/v1/users/{$foreign->id}", ['name' => 'x'])->assertNotFound();
    $this->deleteJson("/api/v1/users/{$foreign->id}")->assertNotFound();
});

it('creates a user with roles and branches', function () {
    $owner = $this->actingAsOwner();
    $branch = Branch::factory()->for($owner->organization)->create();

    $id = $this->postJson('/api/v1/users', [
        'name' => 'مریم کریمی',
        'username' => 'Maryam.K',
        'mobile' => '۰۹۱۲۳۴۵۶۷۸۹',
        'password' => 'secret-pass',
        'roles' => ['cashier'],
        'branch_ids' => [$branch->id],
    ])
        ->assertCreated()
        ->assertJsonPath('data.username', 'maryam.k')
        ->assertJsonPath('data.mobile', '09123456789')
        ->assertJsonPath('data.roles', ['cashier'])
        ->assertJsonPath('data.branches.0.id', $branch->id)
        ->assertJsonPath('data.is_active', true)
        ->json('data.id');

    $user = User::query()->findOrFail($id);
    expect($user->organization_id)->toBe($owner->organization_id)
        ->and(Hash::check('secret-pass', $user->password))->toBeTrue();
});

it('validates user input', function () {
    $owner = $this->actingAsOwner();
    $foreignBranch = Branch::factory()->for(Organization::factory())->create();

    $this->postJson('/api/v1/users', [
        'name' => '',
        'username' => $owner->username,
        'email' => 'not-an-email',
        'mobile' => '12345',
        'password' => 'short',
        'roles' => ['emperor'],
        'branch_ids' => [$foreignBranch->id],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'username', 'email', 'mobile', 'password', 'roles.0', 'branch_ids.0'])
        ->assertJsonPath('errors.username.0', 'این نام کاربری قبلاً ثبت شده است.')
        ->assertJsonPath('errors.mobile.0', 'شماره موبایل باید ۱۱ رقم باشد و با ۰۹ شروع شود.');

    $this->postJson('/api/v1/users', ['name' => 'x', 'username' => 'a b', 'password' => 'long-enough', 'roles' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['username', 'roles']);
});

it('updates a user and keeps the password when none is sent', function () {
    $owner = $this->actingAsOwner();
    $branch = Branch::factory()->for($owner->organization)->create();
    $user = $this->userWithRole('cashier', $owner->organization);
    $oldHash = $user->password;

    $this->putJson("/api/v1/users/{$user->id}", [
        'name' => 'نام جدید',
        'password' => null,
        'roles' => ['driver', 'cashier'],
        'branch_ids' => [$branch->id],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'نام جدید')
        ->assertJsonPath('data.branches.0.id', $branch->id);

    $user->refresh();
    expect($user->password)->toBe($oldHash)
        ->and($user->getRoleNames()->sort()->values()->all())->toBe(['cashier', 'driver']);

    $this->putJson("/api/v1/users/{$user->id}", ['password' => 'brand-new-pass'])->assertOk();
    expect(Hash::check('brand-new-pass', $user->refresh()->password))->toBeTrue();
});

it('records role changes in the audit log', function () {
    $owner = $this->actingAsOwner();
    $user = $this->userWithRole('cashier', $owner->organization);

    $this->putJson("/api/v1/users/{$user->id}", ['roles' => ['driver']])->assertOk();

    $entry = Activity::query()->where('event', 'roles_updated')->where('subject_id', $user->id)->sole();
    expect($entry->causer_id)->toBe($owner->id)
        ->and($entry->attribute_changes->all())->toBe([
            'attributes' => ['roles' => ['driver']],
            'old' => ['roles' => ['cashier']],
        ]);
});

it('soft deletes a user and frees the username', function () {
    $owner = $this->actingAsOwner();
    $user = $this->userWithRole('cashier', $owner->organization);

    $this->deleteJson("/api/v1/users/{$user->id}")->assertNoContent();
    $this->assertSoftDeleted('users', ['id' => $user->id]);

    $this->postJson('/api/v1/users', [
        'name' => 'نفر جدید', 'username' => $user->username, 'password' => 'secret-pass', 'roles' => ['cashier'],
    ])->assertCreated();
});

it('stops users from locking themselves or the organization out', function () {
    $owner = $this->actingAsOwner();

    $this->deleteJson("/api/v1/users/{$owner->id}")
        ->assertConflict()->assertJsonPath('code', 'user_cannot_delete_self');

    $this->putJson("/api/v1/users/{$owner->id}", ['is_active' => false])
        ->assertConflict()->assertJsonPath('code', 'user_cannot_deactivate_self');

    // Demoting the only owner would leave nobody able to manage roles.
    $this->putJson("/api/v1/users/{$owner->id}", ['roles' => ['cashier']])
        ->assertConflict()
        ->assertExactJson(['message' => 'دست‌کم یک مالک فعال باید باقی بماند.', 'code' => 'user_last_owner']);

    expect($owner->refresh()->isOwner())->toBeTrue();
});

it('allows removing an owner while another active owner remains', function () {
    $owner = $this->actingAsOwner();
    $second = $this->owner($owner->organization);
    $inactive = $this->owner($owner->organization);
    $inactive->update(['is_active' => false]);

    $this->putJson("/api/v1/users/{$second->id}", ['roles' => ['accountant']])->assertOk();
    expect($second->refresh()->isOwner())->toBeFalse();

    // The remaining owner is now the last active one; an inactive owner does not count.
    $this->putJson("/api/v1/users/{$owner->id}", ['roles' => ['accountant']])->assertConflict();
});

it('enforces user permissions', function () {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $target = $this->userWithRole('driver', $organization, [$branch]);

    $this->actingAs($this->userWithRole('cashier', $organization, [$branch]));
    $this->getJson('/api/v1/users')->assertForbidden();
    $this->postJson('/api/v1/users', [])->assertForbidden();

    // Branch managers can add and edit staff but not delete them.
    $this->actingAs($this->userWithRole('branch_manager', $organization, [$branch]));
    $this->getJson('/api/v1/users')->assertOk();
    $this->deleteJson("/api/v1/users/{$target->id}")->assertForbidden();
});

it('limits a branch manager to colleagues in their own branches', function () {
    $organization = Organization::factory()->create();
    [$mine, $elsewhere] = Branch::factory()->count(2)->for($organization)->create()->all();
    $manager = $this->userWithRole('branch_manager', $organization, [$mine]);
    $colleague = $this->userWithRole('cashier', $organization, [$mine, $elsewhere]);
    $stranger = $this->userWithRole('cashier', $organization, [$elsewhere]);
    $this->actingAs($manager);

    $this->getJson('/api/v1/users')
        ->assertJsonCount(2, 'data')
        ->assertJsonMissing(['id' => $stranger->id]);
    $this->getJson("/api/v1/users/{$stranger->id}")->assertForbidden();
    $this->putJson("/api/v1/users/{$stranger->id}", ['name' => 'x'])->assertForbidden();

    // They cannot hand out a branch they have no access to ...
    $this->putJson("/api/v1/users/{$colleague->id}", ['branch_ids' => [$elsewhere->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('branch_ids.0');

    // ... and removing a colleague from their branch leaves other assignments alone.
    $this->putJson("/api/v1/users/{$colleague->id}", ['branch_ids' => []])->assertOk();
    expect($colleague->branches()->pluck('branches.id')->all())->toBe([$elsewhere->id]);
});

it('reserves the owner role and owner accounts for owners', function () {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $owner = $this->owner($organization);
    $owner->branches()->attach($branch);
    $this->actingAs($this->userWithRole('branch_manager', $organization, [$branch]));

    $this->postJson('/api/v1/users', [
        'name' => 'x', 'username' => 'sneaky', 'password' => 'secret-pass', 'roles' => ['owner'],
    ])->assertUnprocessable()->assertJsonValidationErrors('roles.0');

    $this->putJson("/api/v1/users/{$owner->id}", ['password' => 'hijacked-pass'])->assertForbidden();
});
