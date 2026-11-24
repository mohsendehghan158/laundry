<?php

declare(strict_types=1);

use App\Modules\Audit\Models\Activity;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Organization;

it('records who created, changed and deleted a branch', function () {
    // A second branch exists so the one under test may be deleted.
    $organization = Organization::factory()->create();
    Branch::factory()->for($organization)->create();
    $owner = $this->actingAsOwner($organization);

    $id = $this->postJson('/api/v1/branches', ['name' => 'ونک', 'code' => 'V1', 'type' => 'store'])->json('data.id');
    $this->putJson("/api/v1/branches/{$id}", ['name' => 'شعبه ونک'])->assertOk();
    $this->deleteJson("/api/v1/branches/{$id}")->assertNoContent();

    $response = $this->getJson('/api/v1/activity-log?filter[log_name]=branch')->assertOk();

    // Newest first.
    expect($response->json('data.*.event'))->toBe(['deleted', 'updated', 'created'])
        ->and($response->json('data.1.causer'))->toBe(['id' => $owner->id, 'name' => $owner->name])
        ->and($response->json('data.1.subject_id'))->toBe($id)
        ->and($response->json('data.1.changes'))->toBe([
            'attributes' => ['name' => 'شعبه ونک'],
            'old' => ['name' => 'ونک'],
        ]);
});

it('never stores passwords in the log', function () {
    $owner = $this->actingAsOwner();
    $user = $this->userWithRole('cashier', $owner->organization);

    $this->putJson("/api/v1/users/{$user->id}", ['name' => 'نام تازه', 'password' => 'another-secret'])->assertOk();

    $entry = Activity::query()->where('log_name', 'user')->where('event', 'updated')->sole();
    expect($entry->attribute_changes->all())->toBe([
        'attributes' => ['name' => 'نام تازه'],
        'old' => ['name' => $user->name],
    ]);
});

it('filters by event, user and time', function () {
    $owner = $this->actingAsOwner();
    $other = $this->owner($owner->organization);

    $this->travelTo('2026-03-01 10:00:00');
    Branch::factory()->for($owner->organization)->create();
    $this->travelTo('2026-03-05 10:00:00');
    $this->actingAs($other);
    Branch::factory()->for($owner->organization)->create();
    $this->actingAs($owner);

    $this->getJson('/api/v1/activity-log?filter[event]=created&filter[log_name]=branch')
        ->assertJsonPath('meta.total', 2);
    $this->getJson("/api/v1/activity-log?filter[log_name]=branch&filter[causer_id]={$other->id}")
        ->assertJsonPath('meta.total', 1);
    $this->getJson('/api/v1/activity-log?filter[log_name]=branch&filter[from]=2026-03-02T00:00:00Z')
        ->assertJsonPath('meta.total', 1);
    $this->getJson('/api/v1/activity-log?filter[log_name]=branch&filter[to]=2026-03-02T00:00:00Z')
        ->assertJsonPath('meta.total', 1);
});

it('shows only entries of the own organization', function () {
    $this->actingAsOwner();
    $this->postJson('/api/v1/branches', ['name' => 'الف', 'code' => 'A1', 'type' => 'store'])->assertCreated();

    $this->actingAsOwner();
    $this->getJson('/api/v1/activity-log')->assertOk()->assertJsonPath('meta.total', 0);
});

it('requires permission to read the log', function () {
    $this->actingAs($this->userWithRole('cashier'));

    $this->getJson('/api/v1/activity-log')->assertForbidden();
});
