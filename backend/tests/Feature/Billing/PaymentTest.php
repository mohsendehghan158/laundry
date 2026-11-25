<?php

declare(strict_types=1);

use App\Modules\Billing\Models\PromoCode;
use App\Modules\Orders\Enums\DiscountType;
use App\Modules\Orders\Models\Order;
use App\Modules\Organization\Models\Branch;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-24 10:00', 'Asia/Tehran'));
});

// shirtOrder(): one shirt, 900,000 + 10% VAT = 990,000 rials (tests/Support/production.php).

function openRegister(object $shop, int $float = 500_000): array
{
    return test()->postJson('/api/v1/cash-shifts', ['branch_id' => $shop->branch->id, 'opening_float' => $float])->assertCreated()->json('data');
}

it('opens one register per branch and shows it as current', function () {
    $shop = shop();
    $this->actingAs(staff($shop));

    $this->getJson("/api/v1/cash-shifts/current?branch_id={$shop->branch->id}")->assertOk()->assertJsonPath('data', null);

    $shift = openRegister($shop);
    expect($shift['status'])->toBe('open')->and($shift['totals']['expected_cash'])->toBe(500_000);

    $this->postJson('/api/v1/cash-shifts', ['branch_id' => $shop->branch->id, 'opening_float' => 0])
        ->assertConflict()
        ->assertJsonPath('code', 'shift_already_open');

    $this->getJson("/api/v1/cash-shifts/current?branch_id={$shop->branch->id}")->assertJsonPath('data.id', $shift['id']);

    $other = Branch::factory()->for($shop->organization)->create();
    $this->getJson("/api/v1/cash-shifts/current?branch_id={$other->id}")->assertNotFound();
});

it('takes payments in parts with different methods and tracks the balance', function () {
    $shop = shop();
    $this->actingAs(staff($shop));
    $order = shirtOrder($shop);

    // Cash needs an open register; a card does not.
    $this->postJson("/api/v1/orders/{$order['id']}/payments", ['method' => 'cash', 'amount' => 400_000])
        ->assertConflict()
        ->assertJsonPath('code', 'shift_not_open');
    $this->postJson("/api/v1/orders/{$order['id']}/payments", ['method' => 'card', 'amount' => 400_000, 'reference' => '۱۲۳۴۵۶'])
        ->assertCreated()
        ->assertJsonPath('data.reference', '123456');

    openRegister($shop);
    $this->postJson("/api/v1/orders/{$order['id']}/payments", ['method' => 'cash', 'amount' => 600_000])
        ->assertConflict()
        ->assertJsonPath('code', 'payment_exceeds_balance');
    $this->postJson("/api/v1/orders/{$order['id']}/payments", ['method' => 'cash', 'amount' => 590_000])->assertCreated();

    $this->getJson("/api/v1/orders/{$order['id']}")
        ->assertJsonPath('data.paid_amount', 990_000)
        ->assertJsonPath('data.balance', 0)
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.payments.*.method', ['card', 'cash'])
        ->assertJsonPath('data.version', 3);
});

it('marks a partly paid order and refuses payments on cancelled ones', function () {
    $shop = shop();
    $this->actingAs(staff($shop));
    $order = shirtOrder($shop);

    $this->postJson("/api/v1/orders/{$order['id']}/payments", ['method' => 'card', 'amount' => 100_000])->assertCreated();
    $this->getJson("/api/v1/orders/{$order['id']}")->assertJsonPath('data.payment_status', 'partially_paid');

    $second = shirtOrder($shop);
    Order::query()->whereKey($second['id'])->update(['status' => 'cancelled']);
    $this->postJson("/api/v1/orders/{$second['id']}/payments", ['method' => 'card', 'amount' => 1])
        ->assertConflict()
        ->assertJsonPath('code', 'payment_order_cancelled');
});

it('refunds up to what was paid, so the order can then be cancelled', function () {
    $shop = shop();
    $this->actingAs(staff($shop, 'branch_manager'));
    $order = shirtOrder($shop);
    $this->postJson("/api/v1/orders/{$order['id']}/payments", ['method' => 'card', 'amount' => 990_000])->assertCreated();

    $this->postJson("/api/v1/orders/{$order['id']}/transitions", ['to' => 'cancelled', 'reason' => 'x'])
        ->assertConflict()
        ->assertJsonPath('code', 'order_has_payments');

    $this->postJson("/api/v1/orders/{$order['id']}/refunds", ['method' => 'card', 'amount' => 2_000_000, 'reason' => 'x'])
        ->assertConflict()
        ->assertJsonPath('code', 'refund_exceeds_paid');
    $this->postJson("/api/v1/orders/{$order['id']}/refunds", ['method' => 'card', 'amount' => 990_000])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');
    $this->postJson("/api/v1/orders/{$order['id']}/refunds", ['method' => 'card', 'amount' => 990_000, 'reason' => 'لغو به درخواست مشتری'])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'refund');

    $this->getJson("/api/v1/orders/{$order['id']}")
        ->assertJsonPath('data.paid_amount', 0)
        ->assertJsonPath('data.payment_status', 'refunded');
    $this->postJson("/api/v1/orders/{$order['id']}/transitions", ['to' => 'cancelled', 'reason' => 'لغو'])->assertOk();

    expect(Activity::query()->where('event', 'refunded')->sole()->properties['reason'])->toBe('لغو به درخواست مشتری');
});

it('lets only those with the right take payments and refunds', function () {
    $shop = shop();
    $this->actingAs(staff($shop));
    $order = shirtOrder($shop);
    $this->postJson("/api/v1/orders/{$order['id']}/payments", ['method' => 'card', 'amount' => 1000])->assertCreated();
    $this->postJson("/api/v1/orders/{$order['id']}/refunds", ['method' => 'card', 'amount' => 1000, 'reason' => 'x'])->assertForbidden();

    $this->actingAs(staff($shop, 'plant_operator'));
    $this->postJson("/api/v1/orders/{$order['id']}/payments", ['method' => 'card', 'amount' => 1000])->assertForbidden();

    $foreign = Order::factory()->create(['organization_id' => $shop->organization->id, 'branch_id' => Branch::factory()->for($shop->organization)]);
    $this->actingAs(staff($shop));
    $this->postJson("/api/v1/orders/{$foreign->id}/payments", ['method' => 'card', 'amount' => 1000])->assertForbidden();
});

it('closes a register with expected cash and the difference counted', function () {
    $shop = shop();
    $this->actingAs(staff($shop));
    $shift = openRegister($shop, 500_000);
    $first = shirtOrder($shop);
    $second = shirtOrder($shop);

    $this->postJson("/api/v1/orders/{$first['id']}/payments", ['method' => 'cash', 'amount' => 990_000])->assertCreated();
    $this->postJson("/api/v1/orders/{$second['id']}/payments", ['method' => 'card', 'amount' => 990_000])->assertCreated();
    $this->postJson("/api/v1/cash-shifts/{$shift['id']}/movements", ['type' => 'out', 'amount' => 10_000_000, 'reason' => 'خرید'])
        ->assertConflict()
        ->assertJsonPath('code', 'shift_cash_insufficient');
    $this->postJson("/api/v1/cash-shifts/{$shift['id']}/movements", ['type' => 'out', 'amount' => 200_000, 'reason' => 'خرید چوب‌لباسی'])->assertOk();
    $this->postJson("/api/v1/cash-shifts/{$shift['id']}/movements", ['type' => 'in', 'amount' => 50_000, 'reason' => 'پول خرد'])->assertOk();

    // 500,000 + 990,000 − 200,000 + 50,000 = 1,340,000 expected; 1,330,000 counted.
    $this->postJson("/api/v1/cash-shifts/{$shift['id']}/close", ['counted_cash' => 1_330_000])
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.expected_cash', 1_340_000)
        ->assertJsonPath('data.difference', -10_000)
        ->assertJsonPath('data.totals.by_method', ['cash' => 990_000, 'card' => 990_000, 'transfer' => 0])
        ->assertJsonPath('data.movements.0.reason', 'خرید چوب‌لباسی');

    $this->postJson("/api/v1/cash-shifts/{$shift['id']}/close", ['counted_cash' => 0])->assertConflict()->assertJsonPath('code', 'shift_not_open');
    $this->postJson("/api/v1/orders/{$second['id']}/refunds", ['method' => 'cash', 'amount' => 1, 'reason' => 'x'])->assertForbidden();

    $this->getJson('/api/v1/cash-shifts')->assertJsonPath('meta.total', 1);
    expect(Activity::query()->where('event', 'shift_closed')->sole()->properties['difference'])->toBe(-10_000);
});

it('applies a promo code with its rules', function () {
    $shop = shop();
    $this->actingAs(staff($shop));
    PromoCode::query()->forceCreate([
        'organization_id' => $shop->organization->id, 'code' => 'WELCOME', 'type' => DiscountType::Percent,
        'value' => 2000, 'max_discount' => 150_000, 'first_order_only' => true,
    ]);
    PromoCode::query()->forceCreate([
        'organization_id' => $shop->organization->id, 'code' => 'BIG', 'type' => DiscountType::Amount,
        'value' => 100_000, 'min_order' => 5_000_000,
    ]);
    $shirt = ['lines' => [['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'quantity' => 1]]];

    // 20% of 900,000 = 180,000, capped at 150,000.
    $this->postJson('/api/v1/orders/price-preview', [...$shirt, 'promo_code' => 'welcome', 'customer_id' => $shop->customer->id])
        ->assertOk()
        ->assertJsonPath('data.discount_amount', 150_000)
        ->assertJsonPath('data.total', 825_000);

    $this->postJson('/api/v1/orders', orderPayload($shop, [...$shirt, 'promo_code' => 'WELCOME']))
        ->assertCreated()
        ->assertJsonPath('data.promo_code.code', 'WELCOME')
        ->assertJsonPath('data.discount.amount', 150_000)
        ->assertJsonPath('data.discount.reason', 'کد تخفیف WELCOME');

    $this->postJson('/api/v1/orders', orderPayload($shop, [...$shirt, 'promo_code' => 'WELCOME']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['promo_code' => 'این کد فقط برای اولین سفارش مشتری است.']);
    $this->postJson('/api/v1/orders', orderPayload($shop, [...$shirt, 'promo_code' => 'BIG']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['promo_code' => 'این کد برای سفارش‌های دست‌کم 5,000,000 ریال است.']);
    $this->postJson('/api/v1/orders', orderPayload($shop, [...$shirt, 'promo_code' => 'NOPE']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('promo_code');
    $this->postJson('/api/v1/orders', orderPayload($shop, [...$shirt, 'promo_code' => 'BIG', 'discount' => ['type' => 'percent', 'value' => 5]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('promo_code');
});

it('stops a promo code at its usage limit, not counting cancelled orders', function () {
    $shop = shop();
    $this->actingAs(staff($shop, 'branch_manager'));
    PromoCode::query()->forceCreate(['organization_id' => $shop->organization->id, 'code' => 'ONCE', 'type' => DiscountType::Amount, 'value' => 10_000, 'usage_limit' => 1]);
    $payload = orderPayload($shop, ['promo_code' => 'ONCE']);

    $first = $this->postJson('/api/v1/orders', $payload)->assertCreated()->json('data.id');
    $this->postJson('/api/v1/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('promo_code');

    $this->postJson("/api/v1/orders/{$first}/transitions", ['to' => 'cancelled', 'reason' => 'x'])->assertOk();
    $this->postJson('/api/v1/orders', $payload)->assertCreated();
});

it('manages promo codes', function () {
    $owner = $this->actingAsOwner();

    $id = $this->postJson('/api/v1/promo-codes', ['code' => 'yalda-۱۴۰۵', 'type' => 'percent', 'value' => 12.5, 'ends_at' => '2026-12-21T23:59:00Z'])
        ->assertCreated()
        ->assertJsonPath('data.code', 'YALDA-1405')
        ->assertJsonPath('data.value', 12.5)
        ->json('data.id');

    $this->postJson('/api/v1/promo-codes', ['code' => 'YALDA-1405', 'type' => 'amount', 'value' => 1])->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->postJson('/api/v1/promo-codes', ['code' => 'X', 'type' => 'percent', 'value' => 150])->assertUnprocessable()->assertJsonValidationErrors('value');
    $this->putJson("/api/v1/promo-codes/{$id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
    $this->getJson('/api/v1/promo-codes')->assertJsonPath('data.0.used_count', 0);

    $this->actingAs($this->userWithRole('branch_manager', $owner->organization));
    $this->getJson('/api/v1/promo-codes')->assertForbidden();
});
