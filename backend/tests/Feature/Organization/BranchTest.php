<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Organization;

it('lists the branches of the organization with paging, search and sorting', function () {
    $owner = $this->actingAsOwner();
    Branch::factory()->for($owner->organization)->create(['name' => 'شعبه ونک', 'code' => 'V1', 'type' => 'store']);
    Branch::factory()->for($owner->organization)->create(['name' => 'شعبه تجریش', 'code' => 'T1', 'type' => 'store']);
    Branch::factory()->for($owner->organization)->create(['name' => 'کارگاه', 'code' => 'P1', 'type' => 'plant', 'is_active' => false]);

    $this->getJson('/api/v1/branches?sort=code')
        ->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.*.code', ['P1', 'T1', 'V1'])
        ->assertJsonStructure(['data' => [['id', 'name', 'code', 'type', 'phone', 'address', 'is_active', 'users_count']]]);

    $this->getJson('/api/v1/branches?sort=-code&per_page=2')
        ->assertJsonPath('data.*.code', ['V1', 'T1'])
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson('/api/v1/branches?filter[q]=تجریش')->assertJsonPath('data.*.code', ['T1']);
    $this->getJson('/api/v1/branches?filter[q]=p1')->assertJsonPath('data.*.code', ['P1']);
    $this->getJson('/api/v1/branches?filter[type]=store')->assertJsonPath('meta.total', 2);
    $this->getJson('/api/v1/branches?filter[is_active]=false')->assertJsonPath('data.*.code', ['P1']);
});

it('rejects unknown filters and sorts instead of ignoring them', function () {
    $this->actingAsOwner();

    $this->getJson('/api/v1/branches?filter[secret]=1')->assertBadRequest()->assertJsonPath('code', 'invalid_query');
    $this->getJson('/api/v1/branches?sort=password')->assertBadRequest()->assertJsonPath('code', 'invalid_query');
});

it('never exposes branches of another organization', function () {
    $owner = $this->actingAsOwner();
    $mine = Branch::factory()->for($owner->organization)->create();
    $foreign = Branch::factory()->for(Organization::factory())->create();

    $this->getJson('/api/v1/branches')->assertJsonPath('data.*.id', [$mine->id]);
    $this->getJson("/api/v1/branches/{$foreign->id}")->assertNotFound();
    $this->putJson("/api/v1/branches/{$foreign->id}", ['name' => 'x'])->assertNotFound();
    $this->deleteJson("/api/v1/branches/{$foreign->id}")->assertNotFound();

    $this->assertDatabaseHas('branches', ['id' => $foreign->id, 'deleted_at' => null]);
});

it('creates a branch in the organization of the signed-in user', function () {
    $owner = $this->actingAsOwner();

    $this->postJson('/api/v1/branches', [
        'name' => 'شعبه سعادت‌آباد',
        'code' => 's۲',
        'type' => 'store',
        'phone' => '۰۲۱-۲۲۳۳۴۴۵۵',
        'address' => 'تهران، سعادت‌آباد',
    ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'S2')
        ->assertJsonPath('data.phone', '021-22334455')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('branches', ['code' => 'S2', 'organization_id' => $owner->organization_id]);
});

it('validates branch input', function () {
    $owner = $this->actingAsOwner();
    Branch::factory()->for($owner->organization)->create(['code' => 'T1']);

    $this->postJson('/api/v1/branches', ['name' => '', 'code' => 'T1', 'type' => 'kiosk', 'phone' => 'abc'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['name', 'code', 'type', 'phone'])
        ->assertJsonPath('errors.name.0', 'نام الزامی است.')
        ->assertJsonPath('errors.code.0', 'این کد قبلاً ثبت شده است.');
});

it('allows the same code in different organizations and after deletion', function () {
    Branch::factory()->for(Organization::factory())->create(['code' => 'T1']);
    $owner = $this->actingAsOwner();
    $first = Branch::factory()->for($owner->organization)->create(['code' => 'T1']);
    Branch::factory()->for($owner->organization)->create();

    $this->deleteJson("/api/v1/branches/{$first->id}")->assertNoContent();

    $this->postJson('/api/v1/branches', ['name' => 'شعبه جدید', 'code' => 'T1', 'type' => 'hybrid'])->assertCreated();
});

it('updates a branch partially', function () {
    $owner = $this->actingAsOwner();
    $branch = Branch::factory()->for($owner->organization)->create(['name' => 'قدیم', 'code' => 'A1']);

    $this->putJson("/api/v1/branches/{$branch->id}", ['name' => 'جدید', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.name', 'جدید')
        ->assertJsonPath('data.code', 'A1')
        ->assertJsonPath('data.is_active', false);

    // Keeping its own code is not a uniqueness conflict.
    $this->putJson("/api/v1/branches/{$branch->id}", ['code' => 'A1'])->assertOk();
});

it('soft deletes a branch but refuses to delete the last one', function () {
    $owner = $this->actingAsOwner();
    $first = Branch::factory()->for($owner->organization)->create();
    $second = Branch::factory()->for($owner->organization)->create();

    $this->deleteJson("/api/v1/branches/{$first->id}")->assertNoContent();
    $this->assertSoftDeleted('branches', ['id' => $first->id]);

    $this->deleteJson("/api/v1/branches/{$second->id}")
        ->assertConflict()
        ->assertExactJson(['message' => 'آخرین شعبه را نمی‌توان حذف کرد.', 'code' => 'branch_last']);
    $this->assertDatabaseHas('branches', ['id' => $second->id, 'deleted_at' => null]);
});

it('shows staff only the branches they are assigned to', function () {
    $organization = Organization::factory()->create();
    $assigned = Branch::factory()->for($organization)->create();
    $other = Branch::factory()->for($organization)->create();
    $this->actingAs($this->userWithRole('branch_manager', $organization, [$assigned]));

    $this->getJson('/api/v1/branches')->assertOk()->assertJsonPath('data.*.id', [$assigned->id]);
    $this->getJson("/api/v1/branches/{$assigned->id}")->assertOk();
    $this->getJson("/api/v1/branches/{$other->id}")->assertForbidden();
});

it('enforces branch permissions', function () {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    Branch::factory()->for($organization)->create();

    // A cashier has no branch permissions at all.
    $this->actingAs($this->userWithRole('cashier', $organization, [$branch]));
    $this->getJson('/api/v1/branches')
        ->assertForbidden()
        ->assertExactJson(['message' => 'شما اجازه انجام این کار را ندارید.', 'code' => 'forbidden']);

    // A branch manager may look but not change.
    $this->actingAs($this->userWithRole('branch_manager', $organization, [$branch]));
    $this->postJson('/api/v1/branches', ['name' => 'x', 'code' => 'X1', 'type' => 'store'])->assertForbidden();
    $this->putJson("/api/v1/branches/{$branch->id}", ['name' => 'x'])->assertForbidden();
    $this->deleteJson("/api/v1/branches/{$branch->id}")->assertForbidden();
});

it('keeps a branch visible to a limited user who creates it', function () {
    $manager = $this->userWithRole('branch_manager');
    $manager->givePermissionTo('branches.create');
    $this->actingAs($manager);

    $id = $this->postJson('/api/v1/branches', ['name' => 'شعبه من', 'code' => 'M1', 'type' => 'store'])
        ->assertCreated()
        ->json('data.id');

    $this->getJson('/api/v1/branches')->assertJsonPath('data.*.id', [$id]);
});
