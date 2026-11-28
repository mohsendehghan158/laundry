<?php

declare(strict_types=1);

use App\Modules\Logistics\Models\Slot;
use App\Modules\Logistics\Models\SlotTemplate;
use App\Modules\Logistics\Models\Zone;
use App\Modules\Notifications\Models\NotificationLog;
use App\Modules\Orders\Models\Order;
use App\Modules\Organization\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

// Saturday 5 December 2026, 08:00 in Tehran; windows can be booked from two hours ahead.
beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-12-05 08:00', 'Asia/Tehran'));
    $this->shop = shop();
    $this->sms = fakeSms();
    $this->zone = Zone::factory()->create(['branch_id' => $this->shop->branch->id, 'name' => 'ونک', 'delivery_fee' => 300_000, 'free_delivery_over' => 5_000_000]);
    foreach ([[6, '09:00', '12:00', 2], [6, '14:00', '16:00', 1], [0, '16:00', '19:00', 3]] as [$weekday, $from, $to, $capacity]) {
        SlotTemplate::query()->forceCreate([
            'organization_id' => $this->shop->organization->id,
            'branch_id' => $this->shop->branch->id,
            'weekday' => $weekday,
            'starts_at' => $from,
            'ends_at' => $to,
            'capacity' => $capacity,
        ]);
    }

    // The demo customer signs in and saves an address in the zone.
    portalSignIn('09121112233', $this->sms);
    $this->home = $this->postJson('/api/v1/customer/addresses', ['title' => 'خانه', 'address' => 'ونک، ملاصدرا، پلاک ۱۲', 'latitude' => 35.76, 'longitude' => 51.41], fromSpa())->json('data.id');
});

/** The id of the window that starts at $time, after fetching the windows (which creates them). */
function windowAt(int $addressId, string $date, string $time): int
{
    test()->getJson("/api/v1/customer/slots?address_id={$addressId}", fromSpa())->assertOk();

    return Slot::query()->whereDate('date', $date)->where('starts_at', "{$time}:00")->sole()->id;
}

function twoShirts(object $shop): array
{
    return [['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'quantity' => 2]];
}

it('lists what a customer can order, with prices', function () {
    $categories = $this->getJson('/api/v1/customer/catalog', fromSpa())->assertOk()->json('data.categories');

    $items = collect($categories[0]['items'])->keyBy('name');
    expect($items['پیراهن']['prices'])->toBe([['service_type_id' => $this->shop->dc->id, 'service_name' => 'خشکشویی', 'price' => 900_000]])
        ->and($items['کیلویی']['unit'])->toBe('kg');
});

it('prices an online order with the delivery fee before it is placed', function () {
    $this->postJson('/api/v1/customer/orders/quote', ['address_id' => $this->home, 'lines' => twoShirts($this->shop)], fromSpa())
        ->assertOk()
        ->assertJsonPath('data.zone.name', 'ونک')
        ->assertJsonPath('data.lines.0.item_name', 'پیراهن')
        ->assertJsonPath('data.subtotal', 1_800_000)
        ->assertJsonPath('data.tax_amount', 180_000)
        ->assertJsonPath('data.items_total', 1_980_000)
        ->assertJsonPath('data.delivery_fee', 300_000)
        ->assertJsonPath('data.total', 2_280_000)
        ->assertJsonPath('data.below_minimum', false);

    // Over the free-delivery threshold the fee goes.
    $suits = [['item_id' => $this->shop->suit->id, 'service_type_id' => $this->shop->dc->id, 'quantity' => 2]];
    $this->postJson('/api/v1/customer/orders/quote', ['address_id' => $this->home, 'lines' => $suits], fromSpa())
        ->assertJsonPath('data.delivery_fee', 0)
        ->assertJsonPath('data.total', 7_040_000);
});

it('places an order for pickup in a booked window and texts a confirmation', function () {
    $slot = windowAt($this->home, '2026-12-05', '14:00');

    $order = $this->postJson('/api/v1/customer/orders', ['address_id' => $this->home, 'slot_id' => $slot, 'lines' => twoShirts($this->shop), 'notes' => 'زنگ دوم'], fromSpa())
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_pickup')
        ->assertJsonPath('data.source', 'online')
        ->assertJsonPath('data.branch_name', $this->shop->branch->name)
        ->assertJsonPath('data.pickup.address', 'ونک، ملاصدرا، پلاک ۱۲')
        ->assertJsonPath('data.pickup.starts_at', '2026-12-05T10:30:00+00:00')
        ->assertJsonPath('data.pickup.ends_at', '2026-12-05T12:30:00+00:00')
        ->assertJsonPath('data.received_at', null)
        ->assertJsonPath('data.delivery_fee', 300_000)
        ->assertJsonPath('data.total', 2_280_000)
        ->assertJsonPath('data.lines.0.quantity', '2.000')
        ->assertJsonPath('data.piece_count', 0)
        ->assertJsonPath('data.can_cancel', true)
        ->json('data');

    // The window lost its only place, and nothing is on the workshop's board yet.
    expect(Slot::query()->findOrFail($slot)->booked)->toBe(1);
    $model = Order::query()->where('public_id', $order['public_id'])->sole();
    expect($model->pickup_slot_id)->toBe($slot)
        ->and($model->created_by)->toBeNull()
        ->and($model->notes)->toBe('زنگ دوم')
        ->and($model->ticket_number)->toBe('T1-00001')
        ->and($model->pieces()->count())->toBe(0)
        ->and($model->history()->sole()->to_status->value)->toBe('pending_pickup');

    $log = NotificationLog::query()->sole();
    expect($log->event->value)->toBe('order_placed')
        ->and($log->body)->toContain('سفارش آنلاین T1-00001')
        ->and($log->body)->toContain('جمع‌آوری: ۱۴۰۵/۰۹/۱۴ ۱۴:۰۰ تا ۱۶:۰۰');
    expect(end($this->sms->sent)['to'])->toBe('09121112233');

    // A full window is no longer offered.
    $this->getJson("/api/v1/customer/slots?address_id={$this->home}", fromSpa())->assertJsonMissing(['id' => $slot]);
});

it('leaves the items for the driver to list', function () {
    $this->zone->update(['min_order' => 3_000_000]);
    $slot = windowAt($this->home, '2026-12-06', '16:00');

    $this->postJson('/api/v1/customer/orders', ['address_id' => $this->home, 'slot_id' => $slot, 'items_by_driver' => true], fromSpa())
        ->assertCreated()
        ->assertJsonPath('data.items_by_driver', true)
        ->assertJsonPath('data.lines', [])
        ->assertJsonPath('data.subtotal', 0)
        ->assertJsonPath('data.delivery_fee', 300_000)
        ->assertJsonPath('data.total', 300_000);

    // Items must be listed by one side or the other, not both.
    $this->postJson('/api/v1/customer/orders', ['address_id' => $this->home, 'slot_id' => $slot], fromSpa())
        ->assertUnprocessable()->assertJsonValidationErrors('lines');
    $this->postJson('/api/v1/customer/orders', ['address_id' => $this->home, 'slot_id' => $slot, 'items_by_driver' => true, 'lines' => twoShirts($this->shop)], fromSpa())
        ->assertUnprocessable()->assertJsonValidationErrors('lines');
});

it('holds to the zone minimum for online orders', function () {
    $this->zone->update(['min_order' => 3_000_000]);
    $slot = windowAt($this->home, '2026-12-06', '16:00');

    $this->postJson('/api/v1/customer/orders/quote', ['address_id' => $this->home, 'lines' => twoShirts($this->shop)], fromSpa())
        ->assertJsonPath('data.min_order', 3_000_000)
        ->assertJsonPath('data.below_minimum', true);
    $this->postJson('/api/v1/customer/orders', ['address_id' => $this->home, 'slot_id' => $slot, 'lines' => twoShirts($this->shop)], fromSpa())
        ->assertUnprocessable()
        ->assertJsonPath('code', 'order_below_minimum');

    expect(Order::query()->count())->toBe(0)
        ->and(Slot::query()->findOrFail($slot)->booked)->toBe(0);
});

it('takes only served addresses and windows that can still be booked', function () {
    $away = $this->postJson('/api/v1/customer/addresses', ['address' => 'لواسان', 'latitude' => 35.82, 'longitude' => 51.63], fromSpa())->json('data.id');
    $slot = windowAt($this->home, '2026-12-05', '14:00');
    $order = fn (array $input) => $this->postJson('/api/v1/customer/orders', [...$input, 'lines' => twoShirts($this->shop)], fromSpa());

    $order(['address_id' => $away, 'slot_id' => $slot])->assertUnprocessable()->assertJsonPath('code', 'address_not_served');

    // Too soon (09:00 is within the two-hour lead time).
    $soon = Slot::query()->whereDate('date', '2026-12-05')->where('starts_at', '09:00:00')->sole();
    $order(['address_id' => $this->home, 'slot_id' => $soon->id])->assertConflict()->assertJsonPath('code', 'slot_unavailable');

    // Another branch's window.
    $west = Branch::factory()->for($this->shop->organization)->create();
    $theirs = Slot::query()->forceCreate(['organization_id' => $this->shop->organization->id, 'branch_id' => $west->id, 'date' => '2026-12-06', 'starts_at' => '10:00', 'ends_at' => '12:00', 'capacity' => 5]);
    $order(['address_id' => $this->home, 'slot_id' => $theirs->id])->assertConflict()->assertJsonPath('code', 'slot_unavailable');

    // A window another customer just filled.
    $order(['address_id' => $this->home, 'slot_id' => $slot])->assertCreated();
    $order(['address_id' => $this->home, 'slot_id' => $slot])->assertConflict()->assertJsonPath('code', 'slot_unavailable');

    // A closed window.
    $sunday = windowAt($this->home, '2026-12-06', '16:00');
    Slot::query()->whereKey($sunday)->update(['is_closed' => true]);
    $order(['address_id' => $this->home, 'slot_id' => $sunday])->assertConflict()->assertJsonPath('code', 'slot_unavailable');
});

it('returns the first order when a placed order is sent again with the same key', function () {
    $slot = windowAt($this->home, '2026-12-06', '16:00');
    $input = ['address_id' => $this->home, 'slot_id' => $slot, 'lines' => twoShirts($this->shop)];

    $first = $this->postJson('/api/v1/customer/orders', $input, [...fromSpa(), 'Idempotency-Key' => 'abc-1'])->assertCreated()->json('data.public_id');
    $again = $this->postJson('/api/v1/customer/orders', $input, [...fromSpa(), 'Idempotency-Key' => 'abc-1'])->assertOk()->json('data.public_id');

    expect($again)->toBe($first)
        ->and(Order::query()->count())->toBe(1)
        ->and(Slot::query()->findOrFail($slot)->booked)->toBe(1);
});

it('lets the customer cancel before pickup and frees the window', function () {
    $slot = windowAt($this->home, '2026-12-06', '16:00');
    $order = $this->postJson('/api/v1/customer/orders', ['address_id' => $this->home, 'slot_id' => $slot, 'items_by_driver' => true], fromSpa())->json('data.public_id');
    expect(Slot::query()->findOrFail($slot)->booked)->toBe(1);

    $this->postJson("/api/v1/customer/orders/{$order}/cancel", [], fromSpa())
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.can_cancel', false);

    expect(Slot::query()->findOrFail($slot)->booked)->toBe(0)
        ->and(Order::query()->sole()->cancel_reason)->toBe('لغو به درخواست مشتری در پرتال')
        ->and(NotificationLog::query()->orderBy('id')->pluck('event')->map->value->all())->toBe(['order_placed', 'order_cancelled']);

    // Once cancelled (or picked up) it stays as it is, and the place is not given back twice.
    $this->postJson("/api/v1/customer/orders/{$order}/cancel", [], fromSpa())->assertConflict()->assertJsonPath('code', 'order_not_cancellable');
    expect(Slot::query()->findOrFail($slot)->booked)->toBe(0);
});

it('does not let a customer reach another customer\'s order', function () {
    $slot = windowAt($this->home, '2026-12-06', '16:00');
    $order = $this->postJson('/api/v1/customer/orders', ['address_id' => $this->home, 'slot_id' => $slot, 'items_by_driver' => true], fromSpa())->json('data.public_id');

    $this->postJson('/api/v1/customer/auth/logout', [], fromSpa())->assertNoContent();
    portalSignIn('09129998877', $this->sms);

    $this->getJson("/api/v1/customer/orders/{$order}", fromSpa())->assertNotFound();
    $this->postJson("/api/v1/customer/orders/{$order}/cancel", [], fromSpa())->assertNotFound();
    $this->postJson('/api/v1/customer/orders', ['address_id' => $this->home, 'slot_id' => $slot, 'items_by_driver' => true], fromSpa())->assertNotFound();
});

it('shows online orders to the branch staff, who can cancel them too', function () {
    $slot = windowAt($this->home, '2026-12-06', '16:00');
    $publicId = $this->postJson('/api/v1/customer/orders', ['address_id' => $this->home, 'slot_id' => $slot, 'lines' => twoShirts($this->shop)], fromSpa())->json('data.public_id');
    $order = Order::query()->where('public_id', $publicId)->sole();

    // Named explicitly: the test application keeps the last request's guard ("customer").
    $this->actingAs(staff($this->shop, 'branch_manager'), 'web');
    $this->getJson('/api/v1/orders?filter[status]=pending_pickup')
        ->assertOk()
        ->assertJsonPath('data.0.ticket_number', 'T1-00001')
        ->assertJsonPath('data.0.source', 'online')
        ->assertJsonPath('data.0.customer.name', 'مشتری نمونه')
        ->assertJsonPath('data.0.pickup.address', 'ونک، ملاصدرا، پلاک ۱۲')
        ->assertJsonPath('data.0.pickup.slot_id', $slot);
    $this->getJson("/api/v1/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.delivery_fee', 300_000)
        ->assertJsonPath('data.available_transitions', ['cancelled']);

    $this->postJson("/api/v1/orders/{$order->id}/transitions", ['to' => 'cancelled', 'reason' => 'مشتری تماس گرفت'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
    expect(Slot::query()->findOrFail($slot)->booked)->toBe(0);
});
