<?php

declare(strict_types=1);

use App\Modules\Organization\Enums\BranchType;
use App\Modules\Organization\Models\Branch;
use App\Modules\Production\Models\Station;

it('lets a manager set up the stations of their branch', function () {
    $shop = shop();
    $this->actingAs(staff($shop, 'branch_manager'));

    $id = $this->postJson('/api/v1/stations', ['branch_id' => $shop->branch->id, 'name' => 'لکه‌گیری', 'code' => 'sp-۱', 'sort_order' => 2])
        ->assertCreated()
        ->assertJsonPath('data.code', 'SP-1')
        ->assertJsonPath('data.kind', 'process')
        ->json('data.id');
    $this->postJson('/api/v1/stations', ['branch_id' => $shop->branch->id, 'name' => 'کنترل', 'code' => 'QC', 'kind' => 'qc', 'sort_order' => 1])->assertCreated();

    // Codes are unique within a branch.
    $this->postJson('/api/v1/stations', ['branch_id' => $shop->branch->id, 'name' => 'دیگر', 'code' => 'SP-1'])->assertJsonValidationErrors('code');

    $this->getJson('/api/v1/stations')->assertJsonPath('data.*.code', ['QC', 'SP-1']);
    $this->putJson("/api/v1/stations/{$id}", ['name' => 'لکه‌بری', 'is_active' => false, 'branch_id' => 9])->assertJsonValidationErrors('branch_id');
    $this->putJson("/api/v1/stations/{$id}", ['name' => 'لکه‌بری', 'is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
    $this->deleteJson("/api/v1/stations/{$id}")->assertNoContent();
    $this->getJson('/api/v1/stations')->assertJsonCount(1, 'data');
});

it('keeps stations to the branches a user works in and to those allowed', function () {
    $shop = shop();
    $other = Branch::factory()->for($shop->organization)->create(['code' => 'P9', 'type' => BranchType::Plant]);
    $foreign = Station::factory()->create(['branch_id' => $other->id]);
    $this->actingAs(staff($shop, 'branch_manager'));

    $this->postJson('/api/v1/stations', ['branch_id' => $other->id, 'name' => 'x', 'code' => 'X'])->assertNotFound();
    $this->putJson("/api/v1/stations/{$foreign->id}", ['name' => 'y'])->assertNotFound();
    $this->getJson('/api/v1/stations')->assertJsonCount(0, 'data');

    $this->actingAs(staff($shop));
    $this->postJson('/api/v1/stations', ['branch_id' => $shop->branch->id, 'name' => 'x', 'code' => 'X'])->assertForbidden();
    $this->getJson('/api/v1/stations')->assertOk();
});
