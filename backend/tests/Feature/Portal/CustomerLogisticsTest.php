<?php

declare(strict_types=1);

use App\Modules\Logistics\Actions\ReserveSlot;
use App\Modules\Logistics\Models\Slot;
use App\Modules\Logistics\Models\SlotTemplate;
use App\Modules\Logistics\Models\Zone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

// Saturday 5 December 2026, 08:00 in Tehran; windows start two hours ahead at the earliest.
beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-12-05 08:00', 'Asia/Tehran'));
    $this->shop = shop();
    $this->sms = fakeSms();
    Zone::factory()->create(['branch_id' => $this->shop->branch->id, 'name' => 'ونک', 'delivery_fee' => 300_000, 'free_delivery_over' => 5_000_000]);
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
});

it('shows on each address whether it is served and what delivery costs', function () {
    portalSignIn('09125556677', $this->sms);

    $this->postJson('/api/v1/customer/addresses', ['title' => 'خانه', 'address' => 'ونک، ملاصدرا', 'latitude' => 35.76, 'longitude' => 51.41], fromSpa())
        ->assertCreated()
        ->assertJsonPath('data.zone.name', 'ونک')
        ->assertJsonPath('data.zone.delivery_fee', 300_000)
        ->assertJsonPath('data.zone.free_delivery_over', 5_000_000);
    $this->postJson('/api/v1/customer/addresses', ['title' => 'ویلا', 'address' => 'لواسان', 'latitude' => 35.82, 'longitude' => 51.63], fromSpa())
        ->assertCreated()
        ->assertJsonPath('data.zone', null);
});

it('offers the open windows of the serving branch, with room and after the lead time', function () {
    portalSignIn('09125556677', $this->sms);
    $home = $this->postJson('/api/v1/customer/addresses', ['address' => 'ونک', 'latitude' => 35.76, 'longitude' => 51.41], fromSpa())->json('data.id');
    $away = $this->postJson('/api/v1/customer/addresses', ['address' => 'لواسان', 'latitude' => 35.82, 'longitude' => 51.63], fromSpa())->json('data.id');

    // 09:00 today starts within two hours, so it is not offered.
    $this->getJson("/api/v1/customer/slots?address_id={$home}", fromSpa())
        ->assertOk()
        ->assertJsonPath('data.zone.name', 'ونک')
        ->assertJsonPath('data.slots.*.date', ['2026-12-05', '2026-12-06'])
        ->assertJsonPath('data.slots.*.starts_at', ['14:00', '16:00'])
        ->assertJsonPath('data.slots.*.remaining', [1, 3])
        ->assertJsonMissingPath('data.slots.0.capacity');

    // A full window drops out.
    app(ReserveSlot::class)->handle(Slot::query()->where('starts_at', '14:00')->sole());
    $this->getJson("/api/v1/customer/slots?address_id={$home}", fromSpa())->assertJsonPath('data.slots.*.starts_at', ['16:00']);

    $this->getJson("/api/v1/customer/slots?address_id={$away}", fromSpa())->assertUnprocessable()->assertJsonPath('code', 'address_not_served');

    // Another customer's address is out of reach.
    $other = $this->shop->customer->addresses()->create(['address' => 'ونک', 'latitude' => 35.76, 'longitude' => 51.41]);
    $this->getJson("/api/v1/customer/slots?address_id={$other->id}", fromSpa())->assertNotFound();
});
