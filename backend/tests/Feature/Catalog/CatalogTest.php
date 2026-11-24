<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\CreateDefaultCatalog;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemCategory;
use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\ServiceType;
use App\Modules\Catalog\Models\TurnaroundTier;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Tenancy\OrganizationContext;
use Spatie\Activitylog\Models\Activity;

function priceCell(Item $item, ServiceType $service, int $price): void
{
    Price::query()->create([
        'price_list_id' => app(OrganizationContext::class)->runFor($item->organization_id, fn () => PriceList::default()->id),
        'item_id' => $item->id,
        'service_type_id' => $service->id,
        'price' => $price,
    ]);
}

it('gives the counter everything it needs in one response, active records only', function () {
    $organization = Organization::factory()->create();
    $this->actingAs($this->userWithRole('cashier', $organization));

    $dryClean = ServiceType::factory()->for($organization)->create(['name' => 'خشکشویی', 'code' => 'DC']);
    $retired = ServiceType::factory()->for($organization)->create(['code' => 'OLD', 'is_active' => false]);
    $clothes = ItemCategory::factory()->for($organization)->create(['name' => 'لباس']);
    $empty = ItemCategory::factory()->for($organization)->create(['name' => 'خالی']);
    $shirt = Item::factory()->for($clothes, 'category')->create(['name' => 'پیراهن']);
    $hidden = Item::factory()->for($clothes, 'category')->create(['is_active' => false]);
    priceCell($shirt, $dryClean, 900_000);
    priceCell($shirt, $retired, 1);
    priceCell($hidden, $dryClean, 1);

    $this->getJson('/api/v1/catalog')
        ->assertOk()
        ->assertJsonPath('data.service_types.*.code', ['DC'])
        ->assertJsonPath('data.categories.*.name', ['لباس'])
        ->assertJsonPath('data.categories.0.items.*.name', ['پیراهن'])
        ->assertJsonPath('data.prices', [['item_id' => $shirt->id, 'service_type_id' => $dryClean->id, 'price' => 900_000]])
        ->assertJsonStructure(['data' => ['upcharges', 'turnaround_tiers', 'defect_types', 'colors']]);

    expect($empty->exists)->toBeTrue();
});

it('lets the counter read the catalog but only managers change it', function () {
    $organization = Organization::factory()->create();
    $service = ServiceType::factory()->for($organization)->create();

    $this->actingAs($this->userWithRole('cashier', $organization));
    $this->getJson('/api/v1/service-types')->assertOk();
    $this->postJson('/api/v1/service-types', ['name' => 'x', 'code' => 'X'])->assertForbidden();
    $this->putJson("/api/v1/service-types/{$service->id}", ['name' => 'x'])->assertForbidden();
    $this->deleteJson("/api/v1/service-types/{$service->id}")->assertForbidden();
    $this->putJson('/api/v1/prices', ['prices' => []])->assertForbidden();

    $this->actingAs($this->userWithRole('driver', $organization));
    $this->getJson('/api/v1/catalog')->assertForbidden();
});

it('manages service types with codes unique per organization', function () {
    $owner = $this->actingAsOwner();
    ServiceType::factory()->for(Organization::factory())->create(['code' => 'DC']);

    $id = $this->postJson('/api/v1/service-types', ['name' => 'خشکشویی', 'code' => 'dc', 'color' => '#1677ff'])
        ->assertCreated()
        ->assertJsonPath('data.code', 'DC')
        ->assertJsonPath('data.is_active', true)
        ->json('data.id');

    $this->postJson('/api/v1/service-types', ['name' => 'تکراری', 'code' => 'DC'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $this->putJson("/api/v1/service-types/{$id}", ['code' => 'DC', 'sort_order' => 5])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 5);

    $this->deleteJson("/api/v1/service-types/{$id}")->assertNoContent();
    $this->postJson('/api/v1/service-types', ['name' => 'دوباره', 'code' => 'DC'])->assertCreated();

    expect(ServiceType::query()->where('organization_id', $owner->organization_id)->count())->toBe(1);
});

it('keeps catalog records of other organizations out of reach', function () {
    $this->actingAsOwner();
    $foreignCategory = ItemCategory::factory()->for(Organization::factory())->create();
    $foreignItem = Item::factory()->for($foreignCategory, 'category')->create();

    $this->getJson('/api/v1/item-categories')->assertJsonCount(0, 'data');
    $this->putJson("/api/v1/items/{$foreignItem->id}", ['name' => 'x'])->assertNotFound();
    $this->deleteJson("/api/v1/item-categories/{$foreignCategory->id}")->assertNotFound();

    $this->postJson('/api/v1/items', ['item_category_id' => $foreignCategory->id, 'name' => 'x', 'unit' => 'piece'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('item_category_id');
});

it('creates items with a unit and the number of pieces each unit makes', function () {
    $owner = $this->actingAsOwner();
    $category = ItemCategory::factory()->for($owner->organization)->create();

    $this->postJson('/api/v1/items', ['item_category_id' => $category->id, 'name' => 'کت‌وشلوار', 'unit' => 'piece', 'pieces_per_unit' => 2])
        ->assertCreated()
        ->assertJsonPath('data.pieces_per_unit', 2)
        ->assertJsonPath('data.unit', 'piece');

    $this->postJson('/api/v1/items', ['item_category_id' => $category->id, 'name' => 'x', 'unit' => 'litre', 'pieces_per_unit' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit', 'pieces_per_unit']);
});

it('refuses to delete a category that still has items', function () {
    $owner = $this->actingAsOwner();
    $category = ItemCategory::factory()->for($owner->organization)->create();
    $item = Item::factory()->for($category, 'category')->create();

    $this->deleteJson("/api/v1/item-categories/{$category->id}")
        ->assertConflict()
        ->assertJsonPath('code', 'category_has_items')
        ->assertJsonPath('message', 'این دسته هنوز قلم دارد؛ اول اقلام را جابه‌جا یا حذف کنید.');

    $this->deleteJson("/api/v1/items/{$item->id}")->assertNoContent();
    $this->deleteJson("/api/v1/item-categories/{$category->id}")->assertNoContent();
});

it('limits percent upcharges to sensible values', function () {
    $this->actingAsOwner();

    $this->postJson('/api/v1/upcharges', ['name' => 'ابریشم', 'type' => 'percent', 'value' => 30])->assertCreated();
    $this->postJson('/api/v1/upcharges', ['name' => 'پلیسه', 'type' => 'fixed', 'value' => 3_000_000])->assertCreated();
    $this->postJson('/api/v1/upcharges', ['name' => 'خطا', 'type' => 'percent', 'value' => 5000])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('value');
});

it('keeps exactly one default turnaround tier', function () {
    $owner = $this->actingAsOwner();
    $normal = TurnaroundTier::factory()->for($owner->organization)->create(['is_default' => true]);

    $express = $this->postJson('/api/v1/turnaround-tiers', ['name' => 'فوری', 'duration_hours' => 24, 'surcharge_percent' => 50, 'is_default' => true])
        ->assertCreated()
        ->assertJsonPath('data.is_default', true)
        ->json('data.id');

    expect($normal->fresh()->is_default)->toBeFalse();

    $this->putJson("/api/v1/turnaround-tiers/{$normal->id}", ['is_default' => true])->assertOk();
    expect(TurnaroundTier::query()->find($express)->is_default)->toBeFalse();

    $this->postJson('/api/v1/turnaround-tiers', ['name' => 'x', 'duration_hours' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('duration_hours');
});

it('sets, changes and removes prices and records each change', function () {
    $owner = $this->actingAsOwner();
    $service = ServiceType::factory()->for($owner->organization)->create();
    $other = ServiceType::factory()->for($owner->organization)->create();
    $item = Item::factory()->for(ItemCategory::factory()->for($owner->organization), 'category')->create();

    $this->putJson('/api/v1/prices', ['prices' => [
        ['item_id' => $item->id, 'service_type_id' => $service->id, 'price' => 900_000],
        ['item_id' => $item->id, 'service_type_id' => $other->id, 'price' => 500_000],
    ]])->assertOk()->assertJsonCount(2, 'data');

    $this->putJson('/api/v1/prices', ['prices' => [
        ['item_id' => $item->id, 'service_type_id' => $service->id, 'price' => 950_000],
        ['item_id' => $item->id, 'service_type_id' => $other->id, 'price' => null],
    ]])
        ->assertOk()
        ->assertJsonPath('data', [['item_id' => $item->id, 'service_type_id' => $service->id, 'price' => 950_000]]);

    $last = Activity::query()->where('event', 'prices_updated')->latest('id')->firstOrFail();
    expect($last->log_name)->toBe('catalog')
        ->and($last->causer_id)->toBe($owner->id)
        ->and($last->properties['prices'])->toBe([
            ['item_id' => $item->id, 'service_type_id' => $service->id, 'old' => 900_000, 'new' => 950_000],
            ['item_id' => $item->id, 'service_type_id' => $other->id, 'old' => 500_000, 'new' => null],
        ]);

    // Nothing changed, nothing logged.
    $count = Activity::query()->where('event', 'prices_updated')->count();
    $this->putJson('/api/v1/prices', ['prices' => [['item_id' => $item->id, 'service_type_id' => $service->id, 'price' => 950_000]]])->assertOk();
    expect(Activity::query()->where('event', 'prices_updated')->count())->toBe($count);
});

it('validates price cells', function () {
    $owner = $this->actingAsOwner();
    $service = ServiceType::factory()->for($owner->organization)->create();
    $foreignItem = Item::factory()->create();

    $this->putJson('/api/v1/prices', ['prices' => [
        ['item_id' => $foreignItem->id, 'service_type_id' => $service->id, 'price' => -5],
    ]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['prices.0.item_id', 'prices.0.price']);
});

it('gives a new organization a starting catalog', function () {
    $organization = Organization::factory()->create();

    app(CreateDefaultCatalog::class)->handle($organization->id);

    app(OrganizationContext::class)->runFor($organization->id, function (): void {
        expect(ServiceType::query()->ordered()->pluck('code')->all())->toBe(['DC', 'WS', 'PR', 'SR'])
            ->and(TurnaroundTier::query()->where('is_default', true)->value('name'))->toBe('عادی')
            ->and(PriceList::query()->where('is_default', true)->count())->toBe(1);
    });
});

it('lists items grouped by category order', function () {
    $owner = $this->actingAsOwner();
    $second = ItemCategory::factory()->for($owner->organization)->create(['sort_order' => 2]);
    $first = ItemCategory::factory()->for($owner->organization)->create(['sort_order' => 1]);
    Item::factory()->for($second, 'category')->create(['name' => 'b0', 'sort_order' => 0]);
    Item::factory()->for($first, 'category')->create(['name' => 'a1', 'sort_order' => 1]);
    Item::factory()->for($first, 'category')->create(['name' => 'a0', 'sort_order' => 0]);

    $this->getJson('/api/v1/items')->assertJsonPath('data.*.name', ['a0', 'a1', 'b0']);
});
