<?php

declare(strict_types=1);

use App\Modules\Catalog\Enums\PriceUnit;
use App\Modules\Catalog\Enums\UpchargeType;
use App\Modules\Catalog\Models\Color;
use App\Modules\Catalog\Models\DefectType;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemCategory;
use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\ServiceType;
use App\Modules\Catalog\Models\TurnaroundTier;
use App\Modules\Catalog\Models\Upcharge;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Piece;
use App\Modules\Organization\Enums\BranchType;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Tenancy\OrganizationContext;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * A small shop: one counter branch, a shirt (DC 900,000), a suit that
 * makes two pieces (DC 3,200,000), washing by weight (WS 800,000 / kg),
 * a 30% silk upcharge, a 72h default tier and a 24h +50% express tier.
 */
function shop(): object
{
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create(['code' => 'T1', 'type' => BranchType::Store]);

    return app(OrganizationContext::class)->runFor($organization->id, function () use ($organization, $branch): object {
        $dc = ServiceType::factory()->for($organization)->create(['name' => 'خشکشویی', 'code' => 'DC']);
        $ws = ServiceType::factory()->for($organization)->create(['name' => 'شست‌وشو', 'code' => 'WS']);
        $category = ItemCategory::factory()->for($organization)->create();
        $shirt = Item::factory()->for($category, 'category')->create(['name' => 'پیراهن']);
        $suit = Item::factory()->for($category, 'category')->create(['name' => 'کت‌وشلوار', 'pieces_per_unit' => 2]);
        $laundry = Item::factory()->for($category, 'category')->create(['name' => 'کیلویی', 'unit' => PriceUnit::Kilogram]);
        $list = PriceList::default();

        foreach ([[$shirt, $dc, 900_000], [$suit, $dc, 3_200_000], [$laundry, $ws, 800_000]] as [$item, $service, $price]) {
            Price::query()->create(['price_list_id' => $list->id, 'item_id' => $item->id, 'service_type_id' => $service->id, 'price' => $price]);
        }

        return (object) [
            'organization' => $organization,
            'branch' => $branch,
            'dc' => $dc,
            'ws' => $ws,
            'shirt' => $shirt,
            'suit' => $suit,
            'laundry' => $laundry,
            'silk' => Upcharge::factory()->for($organization)->create(['name' => 'ابریشم', 'type' => UpchargeType::Percent, 'value' => 30]),
            'normal' => TurnaroundTier::factory()->for($organization)->create(['name' => 'عادی', 'duration_hours' => 72, 'is_default' => true]),
            'express' => TurnaroundTier::factory()->for($organization)->create(['name' => 'فوری', 'duration_hours' => 24, 'surcharge_percent' => 50]),
            'red' => Color::factory()->for($organization)->create(['name' => 'قرمز']),
            'tear' => DefectType::factory()->for($organization)->create(['name' => 'پارگی']),
            'customer' => Customer::factory()->for($organization)->create(['name' => 'مشتری نمونه', 'mobile' => '09121112233']),
        ];
    });
}

function staff(object $shop, string $role = 'cashier'): User
{
    return test()->userWithRole($role, $shop->organization, [$shop->branch]);
}

function orderPayload(object $shop, array $overrides = []): array
{
    return [
        'branch_id' => $shop->branch->id,
        'customer_id' => $shop->customer->id,
        'lines' => [
            ['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'quantity' => 3, 'upcharge_ids' => [$shop->silk->id],
                'pieces' => [['color_id' => $shop->red->id, 'brand' => 'Zara', 'defect_type_ids' => [$shop->tear->id], 'notes' => 'یقه']]],
            ['item_id' => $shop->suit->id, 'service_type_id' => $shop->dc->id, 'quantity' => 1],
            ['item_id' => $shop->laundry->id, 'service_type_id' => $shop->ws->id, 'quantity' => '۲٫۵'],
        ],
        ...$overrides,
    ];
}

beforeEach(function () {
    // Tuesday 10:00 in Tehran.
    Carbon::setTestNow(Carbon::parse('2026-11-24 10:00', 'Asia/Tehran'));
});

it('previews the price and due time without saving anything', function () {
    $shop = shop();
    $this->actingAs(staff($shop));

    // Shirts 2,700,000 + silk 810,000; suit 3,200,000; laundry 2.5 × 800,000 = 2,000,000.
    $this->postJson('/api/v1/orders/price-preview', orderPayload($shop))
        ->assertOk()
        ->assertJsonPath('data.subtotal', 8_710_000)
        ->assertJsonPath('data.tax_amount', 871_000)
        ->assertJsonPath('data.total', 9_581_000)
        ->assertJsonPath('data.piece_count', 6)
        ->assertJsonPath('data.turnaround.name', 'عادی')
        ->assertJsonPath('data.lines.0.upcharge_amount', 810_000);

    expect(Order::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('takes an order in with a ticket number, tagged pieces and a due time in working hours', function () {
    $shop = shop();
    $cashier = staff($shop);
    $this->actingAs($cashier);

    $response = $this->postJson('/api/v1/orders', orderPayload($shop, ['turnaround_tier_id' => $shop->express->id, 'notes' => 'عجله دارد']))
        ->assertCreated()
        ->assertJsonPath('data.ticket_number', 'T1-00001')
        ->assertJsonPath('data.status', 'received')
        ->assertJsonPath('data.payment_status', 'unpaid')
        ->assertJsonPath('data.paid_amount', 0)
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.customer.name', 'مشتری نمونه')
        ->assertJsonPath('data.turnaround.surcharge_percent', 50)
        ->assertJsonPath('data.piece_count', 6)
        ->assertJsonPath('data.lines.0.item_name', 'پیراهن')
        ->assertJsonPath('data.lines.0.upcharges.0.name', 'ابریشم')
        ->assertJsonPath('data.lines.2.quantity', 2.5)
        ->assertJsonPath('data.lines.0.pieces.0.tag_code', 'T1-00001-01')
        ->assertJsonPath('data.lines.0.pieces.0.color_name', 'قرمز')
        ->assertJsonPath('data.lines.0.pieces.0.brand', 'Zara')
        ->assertJsonPath('data.lines.0.pieces.0.defects.0.name', 'پارگی')
        ->assertJsonPath('data.lines.1.pieces.*.tag_code', ['T1-00001-04', 'T1-00001-05'])
        ->assertJsonPath('data.lines.2.pieces.*.tag_code', ['T1-00001-06'])
        ->assertJsonPath('data.history.0.to_status', 'received')
        ->assertJsonPath('data.history.0.user.name', $cashier->name)
        ->assertJsonPath('data.created_by.id', $cashier->id);

    // Express: +50% → 13,065,000 + 10% VAT.
    expect($response->json('data.subtotal'))->toBe(13_065_000)
        ->and($response->json('data.total'))->toBe(14_371_500)
        ->and($response->json('data.public_id'))->toHaveLength(26);

    // 24 working hours from Tuesday 10:00 → Thursday 10:00.
    expect(Carbon::parse($response->json('data.due_at'))->setTimezone('Asia/Tehran')->format('D H:i'))->toBe('Thu 10:00');

    $this->postJson('/api/v1/orders', orderPayload($shop))->assertJsonPath('data.ticket_number', 'T1-00002');
});

it('numbers tickets per branch', function () {
    $shop = shop();
    $second = Branch::factory()->for($shop->organization)->create(['code' => 'T2', 'type' => BranchType::Hybrid]);
    $owner = $this->actingAsOwner($shop->organization);

    $this->postJson('/api/v1/orders', orderPayload($shop))->assertJsonPath('data.ticket_number', 'T1-00001');
    $this->postJson('/api/v1/orders', orderPayload($shop, ['branch_id' => $second->id]))->assertJsonPath('data.ticket_number', 'T2-00001');
    expect($owner)->not->toBeNull();
});

it('returns the same order when a create is retried with the same idempotency key', function () {
    $shop = shop();
    $this->actingAs(staff($shop));

    $first = $this->postJson('/api/v1/orders', orderPayload($shop), ['Idempotency-Key' => 'counter-1-abc'])->assertCreated();
    $this->postJson('/api/v1/orders', orderPayload($shop), ['Idempotency-Key' => 'counter-1-abc'])
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(Order::query()->count())->toBe(1)->and(Piece::query()->count())->toBe(6);
});

it('refuses lines the catalog does not price', function () {
    $shop = shop();
    $this->actingAs(staff($shop));

    $this->postJson('/api/v1/orders', orderPayload($shop, ['lines' => [
        ['item_id' => $shop->shirt->id, 'service_type_id' => $shop->ws->id, 'quantity' => 1],
    ]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines.0.service_type_id' => 'برای «پیراهن» با خدمت «شست‌وشو» قیمتی تعریف نشده است.']);

    $this->postJson('/api/v1/orders', orderPayload($shop, ['lines' => [
        ['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'quantity' => 1.5],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('lines.0.quantity');

    $shop->shirt->update(['is_active' => false]);
    $this->postJson('/api/v1/orders', orderPayload($shop))->assertUnprocessable()->assertJsonValidationErrors('lines.0.item_id');

    $this->postJson('/api/v1/orders', ['branch_id' => $shop->branch->id, 'customer_id' => $shop->customer->id, 'lines' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lines');
});

it('only takes orders in branches the user works in, and never at a plant', function () {
    $shop = shop();
    $other = Branch::factory()->for($shop->organization)->create(['type' => BranchType::Store]);
    $plant = Branch::factory()->for($shop->organization)->create(['type' => BranchType::Plant]);
    $this->actingAs(staff($shop));

    $this->postJson('/api/v1/orders', orderPayload($shop, ['branch_id' => $other->id]))
        ->assertConflict()
        ->assertJsonPath('code', 'order_branch_unavailable');

    $this->actingAsOwner($shop->organization);
    $this->postJson('/api/v1/orders', orderPayload($shop, ['branch_id' => $plant->id]))
        ->assertConflict()
        ->assertJsonPath('code', 'order_branch_not_counter');
});

it('caps manual discounts unless the user may give any discount', function () {
    $shop = shop();

    $this->actingAs(staff($shop));
    $this->postJson('/api/v1/orders', orderPayload($shop, ['discount' => ['type' => 'percent', 'value' => '۱۰', 'reason' => 'مشتری قدیمی']]))
        ->assertCreated()
        ->assertJsonPath('data.discount.amount', 871_000)
        ->assertJsonPath('data.discount.reason', 'مشتری قدیمی');
    $this->postJson('/api/v1/orders', orderPayload($shop, ['discount' => ['type' => 'percent', 'value' => 15]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['discount.value' => 'تخفیف بیشتر از سقف مجاز (10 درصد) است.']);

    $this->actingAs(staff($shop, 'support'));
    $this->postJson('/api/v1/orders', orderPayload($shop, ['discount' => ['type' => 'amount', 'value' => 1000]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['discount.value' => 'شما اجازه دادن تخفیف ندارید.']);

    $this->actingAsOwner($shop->organization);
    $this->postJson('/api/v1/orders', orderPayload($shop, ['discount' => ['type' => 'percent', 'value' => 50]]))->assertCreated();

    expect(Activity::query()->where('event', 'discounted')->count())->toBe(2);
});

it('lists orders of the user\'s branches with search and filters', function () {
    $shop = shop();
    $cashier = staff($shop);
    $this->actingAs($cashier);
    $first = $this->postJson('/api/v1/orders', orderPayload($shop))->json('data');
    $other = Customer::factory()->for($shop->organization)->create(['name' => 'دیگری', 'mobile' => '09359998877']);
    $this->postJson('/api/v1/orders', orderPayload($shop, ['customer_id' => $other->id]));
    $hidden = Order::factory()->create(['organization_id' => $shop->organization->id, 'branch_id' => Branch::factory()->for($shop->organization)]);

    $this->getJson('/api/v1/orders')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.piece_count', 6);
    $this->getJson('/api/v1/orders?filter[q]=۰۰۰۰۱')->assertJsonPath('data.*.ticket_number', ['T1-00001']);
    $this->getJson('/api/v1/orders?filter[q]=t1-00002')->assertJsonPath('data.*.customer.name', ['دیگری']);
    $this->getJson('/api/v1/orders?filter[q]=۰۹۳۵۹')->assertJsonPath('data.*.customer.name', ['دیگری']);
    $this->getJson('/api/v1/orders?filter[status]=ready')->assertJsonPath('meta.total', 0);
    $this->getJson("/api/v1/orders/{$hidden->id}")->assertForbidden();

    // 72 working hours from Tuesday 10:00 is the next Tuesday 10:00.
    Carbon::setTestNow(now()->addDays(7));
    $this->getJson('/api/v1/orders?filter[due]=overdue')->assertJsonPath('meta.total', 0);
    $this->getJson('/api/v1/orders?filter[due]=today')->assertJsonPath('meta.total', 2);
    Carbon::setTestNow(now()->addMinute());
    $this->getJson('/api/v1/orders?filter[due]=overdue&sort=due_at')->assertJsonPath('meta.total', 2);
    expect($first['id'])->toBeInt();
});

it('moves an order through its statuses and keeps pieces in step', function () {
    $shop = shop();
    $cashier = staff($shop);
    $this->actingAs($cashier);
    $id = $this->postJson('/api/v1/orders', orderPayload($shop))->json('data.id');

    $this->postJson("/api/v1/orders/{$id}/transitions", ['to' => 'ready', 'rack_location' => 'A-12'])
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.rack_location', 'A-12')
        ->assertJsonPath('data.available_transitions', ['in_process'])
        ->assertJsonPath('data.lines.0.pieces.0.status', 'ready');

    $this->postJson("/api/v1/orders/{$id}/transitions", ['to' => 'cancelled', 'reason' => 'x'])->assertForbidden();

    $this->postJson("/api/v1/orders/{$id}/transitions", ['to' => 'in_process'])
        ->assertOk()
        ->assertJsonPath('data.lines.0.pieces.0.status', 'received')
        ->assertJsonPath('data.history.*.to_status', ['received', 'ready', 'in_process']);

    $this->actingAs(staff($shop, 'branch_manager'));
    $this->postJson("/api/v1/orders/{$id}/transitions", ['to' => 'cancelled'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson("/api/v1/orders/{$id}/transitions", ['to' => 'cancelled', 'reason' => 'مشتری منصرف شد'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancel_reason', 'مشتری منصرف شد')
        ->assertJsonPath('data.available_transitions', []);

    $this->postJson("/api/v1/orders/{$id}/transitions", ['to' => 'ready'])
        ->assertConflict()
        ->assertJsonPath('code', 'order_transition_not_allowed')
        ->assertJsonPath('message', 'سفارش از وضعیت «لغوشده» به «آماده تحویل» نمی‌رود.');
});

it('edits the lines of an order before work starts, guarding against stale edits', function () {
    $shop = shop();
    $this->actingAs(staff($shop));
    $order = $this->postJson('/api/v1/orders', orderPayload($shop))->json('data');

    $lines = [['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'quantity' => 2]];

    $this->putJson("/api/v1/orders/{$order['id']}", ['version' => 1, 'lines' => $lines])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    $this->putJson("/api/v1/orders/{$order['id']}", ['version' => 1, 'reason' => 'مشتری یک پیراهن را برد', 'lines' => $lines])
        ->assertOk()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.total', 1_980_000)
        ->assertJsonPath('data.ticket_number', 'T1-00001')
        ->assertJsonPath('data.lines.0.pieces.*.tag_code', ['T1-00001-01', 'T1-00001-02']);

    expect(Piece::query()->count())->toBe(2)
        ->and(Activity::query()->where('event', 'edited')->sole()->properties['reason'])->toBe('مشتری یک پیراهن را برد');

    $this->putJson("/api/v1/orders/{$order['id']}", ['version' => 1, 'reason' => 'x', 'lines' => $lines])
        ->assertConflict()
        ->assertJsonPath('code', 'order_version_conflict');

    $this->postJson("/api/v1/orders/{$order['id']}/transitions", ['to' => 'in_process'])->assertOk();
    $this->putJson("/api/v1/orders/{$order['id']}", ['version' => 3, 'reason' => 'x', 'lines' => $lines])
        ->assertConflict()
        ->assertJsonPath('code', 'order_not_editable');
});

it('keeps the prices an order was taken at when the catalog changes', function () {
    $shop = shop();
    $this->actingAs(staff($shop));
    $id = $this->postJson('/api/v1/orders', orderPayload($shop))->json('data.id');

    Price::query()->update(['price' => 1]);
    $shop->shirt->update(['name' => 'نام جدید']);

    $this->getJson("/api/v1/orders/{$id}")
        ->assertJsonPath('data.lines.0.item_name', 'پیراهن')
        ->assertJsonPath('data.lines.0.unit_price', 900_000);
});

it('keeps orders of other organizations out of reach', function () {
    $shop = shop();
    $this->actingAs(staff($shop));
    $foreign = Order::factory()->create();

    $this->getJson("/api/v1/orders/{$foreign->id}")->assertNotFound();
    $this->postJson("/api/v1/orders/{$foreign->id}/transitions", ['to' => 'ready'])->assertNotFound();
    $this->postJson('/api/v1/orders', orderPayload($shop, ['customer_id' => Customer::factory()->create()->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('customer_id');
});
