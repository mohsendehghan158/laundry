<?php

declare(strict_types=1);

use App\Modules\Logistics\Database\Factories\ZoneFactory;
use App\Modules\Logistics\Models\Zone;
use App\Modules\Organization\Enums\BranchType;
use App\Modules\Organization\Models\Branch;

it('lets a manager draw the zones their branch serves', function () {
    $shop = shop();
    $this->actingAs(staff($shop, 'branch_manager'));

    $id = $this->postJson('/api/v1/zones', [
        'branch_id' => $shop->branch->id,
        'name' => 'ونک',
        'polygon' => ZoneFactory::VANAK,
        'delivery_fee' => 300_000,
        'free_delivery_over' => 5_000_000,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'ونک')
        ->assertJsonPath('data.branch_name', $shop->branch->name)
        ->assertJsonPath('data.polygon', [[35.75, 51.4], [35.75, 51.42], [35.77, 51.42], [35.77, 51.4]])
        ->assertJsonPath('data.min_order', null)
        ->json('data.id');
    expect((float) Zone::query()->findOrFail($id)->max_latitude)->toBe(35.77);

    $this->postJson('/api/v1/zones', ['branch_id' => $shop->branch->id, 'name' => 'خط', 'polygon' => [[35.7, 51.4], [35.71, 51.41]]])
        ->assertJsonValidationErrors('polygon');

    // Reshaping moves the bounding box with it.
    $this->putJson("/api/v1/zones/{$id}", ['polygon' => [[35.70, 51.30], [35.70, 51.35], [35.74, 51.35]], 'min_order' => 1_000_000])
        ->assertOk()
        ->assertJsonPath('data.min_order', 1_000_000)
        ->assertJsonCount(3, 'data.polygon');
    expect((float) Zone::query()->findOrFail($id)->max_latitude)->toBe(35.74);

    $this->getJson('/api/v1/zones')->assertJsonCount(1, 'data');
    $this->deleteJson("/api/v1/zones/{$id}")->assertNoContent();
    $this->getJson('/api/v1/zones')->assertJsonCount(0, 'data');
});

it('keeps zones to the branches a user works in and to those allowed', function () {
    $shop = shop();
    $other = Branch::factory()->for($shop->organization)->create(['code' => 'S9', 'type' => BranchType::Store]);
    $foreign = Zone::factory()->create(['branch_id' => $other->id]);
    $this->actingAs(staff($shop, 'branch_manager'));

    $this->postJson('/api/v1/zones', ['branch_id' => $other->id, 'name' => 'x', 'polygon' => ZoneFactory::VANAK])->assertNotFound();
    $this->putJson("/api/v1/zones/{$foreign->id}", ['name' => 'y'])->assertNotFound();
    $this->deleteJson("/api/v1/zones/{$foreign->id}")->assertNotFound();
    $this->getJson('/api/v1/zones')->assertJsonCount(0, 'data');

    // A cashier neither sees nor draws zones.
    $this->actingAs(staff($shop));
    $this->getJson('/api/v1/zones')->assertForbidden();
    $this->postJson('/api/v1/zones', ['branch_id' => $shop->branch->id, 'name' => 'x', 'polygon' => ZoneFactory::VANAK])->assertForbidden();
});

it('tells anyone whether a point is served and what delivery costs there', function () {
    $shop = shop();
    Zone::factory()->create(['branch_id' => $shop->branch->id, 'name' => 'ونک', 'delivery_fee' => 300_000, 'min_order' => 1_000_000]);
    // Drawn later over the same area: the first zone keeps it.
    Zone::factory()->create(['branch_id' => $shop->branch->id, 'name' => 'بزرگ']);
    Zone::factory()->shaped([[35.60, 51.30], [35.60, 51.32], [35.62, 51.32], [35.62, 51.30]])->create(['branch_id' => $shop->branch->id, 'name' => 'خاموش', 'is_active' => false]);

    $this->getJson('/api/v1/public/zones/check?latitude=35.76&longitude=51.41')
        ->assertOk()
        ->assertJsonPath('data.served', true)
        ->assertJsonPath('data.zone.name', 'ونک')
        ->assertJsonPath('data.zone.delivery_fee', 300_000)
        ->assertJsonPath('data.zone.min_order', 1_000_000)
        ->assertJsonPath('data.zone.branch_name', $shop->branch->name);

    $this->getJson('/api/v1/public/zones/check?latitude=35.61&longitude=51.31')->assertJsonPath('data', ['served' => false, 'zone' => null]);
    $this->getJson('/api/v1/public/zones/check?latitude=35.80&longitude=51.41')->assertJsonPath('data.served', false);
    $this->getJson('/api/v1/public/zones/check?latitude=200&longitude=51.41')->assertJsonValidationErrors('latitude');

    // A zone of a closed branch serves nobody.
    $shop->branch->update(['is_active' => false]);
    $this->getJson('/api/v1/public/zones/check?latitude=35.76&longitude=51.41')->assertJsonPath('data.served', false);
});

it('waives the delivery fee from the free-delivery total', function () {
    $zone = new Zone(['delivery_fee' => 300_000, 'free_delivery_over' => 5_000_000]);

    expect($zone->deliveryFeeFor(4_999_999))->toBe(300_000)
        ->and($zone->deliveryFeeFor(5_000_000))->toBe(0)
        ->and((new Zone(['delivery_fee' => 300_000]))->deliveryFeeFor(99_000_000))->toBe(300_000);
});
