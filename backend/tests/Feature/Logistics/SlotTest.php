<?php

declare(strict_types=1);

use App\Modules\Logistics\Actions\ReserveSlot;
use App\Modules\Logistics\Models\Slot;
use App\Modules\Organization\Enums\BranchType;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Support\Settings;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

// Saturday 5 December 2026, 08:00 in Tehran. Weekdays: 0 = Sunday … 6 = Saturday.
beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-12-05 08:00', 'Asia/Tehran'));
    $this->shop = shop();
    $this->actingAs(staff($this->shop, 'branch_manager'));
});

function window(object $shop, int $weekday, string $from, string $to, int $capacity): array
{
    return test()->postJson('/api/v1/slot-templates', ['branch_id' => $shop->branch->id, 'weekday' => $weekday, 'starts_at' => $from, 'ends_at' => $to, 'capacity' => $capacity])
        ->assertCreated()
        ->json('data');
}

function slotsOf(object $shop): array
{
    return array_map(
        fn (array $slot): string => "{$slot['date']} {$slot['starts_at']} {$slot['booked']}/{$slot['capacity']}",
        test()->getJson("/api/v1/slots?branch_id={$shop->branch->id}")->assertOk()->json('data'),
    );
}

it('turns weekly windows into dated slots for the days customers can book', function () {
    $saturday = window($this->shop, 6, '۰۹:۰۰', '12:00', 2);
    expect($saturday['starts_at'])->toBe('09:00');
    window($this->shop, 0, '16:00', '19:00', 3);

    // Windows of one day may not overlap, and must end after they start.
    $this->postJson('/api/v1/slot-templates', ['branch_id' => $this->shop->branch->id, 'weekday' => 6, 'starts_at' => '11:00', 'ends_at' => '13:00', 'capacity' => 1])
        ->assertJsonValidationErrors('starts_at');
    $this->postJson('/api/v1/slot-templates', ['branch_id' => $this->shop->branch->id, 'weekday' => 0, 'starts_at' => '20:00', 'ends_at' => '19:00', 'capacity' => 1])
        ->assertJsonValidationErrors('ends_at');

    // Seven days from today: one Saturday, one Sunday.
    expect(slotsOf($this->shop))->toBe(['2026-12-05 09:00 0/2', '2026-12-06 16:00 0/3']);

    app(Settings::class)->put(['logistics.booking_days' => 14]);
    expect(slotsOf($this->shop))->toHaveCount(4);

    $this->getJson('/api/v1/slot-templates')->assertJsonPath('data.*.weekday', [0, 6]);
});

it('moves future unbooked slots with their window and keeps booked ones', function () {
    $saturday = window($this->shop, 6, '09:00', '12:00', 2);
    $sunday = window($this->shop, 0, '16:00', '19:00', 3);
    slotsOf($this->shop);
    app(ReserveSlot::class)->handle(Slot::query()->where('date', '2026-12-06')->sole());

    $this->putJson("/api/v1/slot-templates/{$sunday['id']}", ['starts_at' => '17:00', 'ends_at' => '20:00'])->assertOk()->assertJsonPath('data.starts_at', '17:00');
    $this->putJson("/api/v1/slot-templates/{$saturday['id']}", ['capacity' => 4])->assertOk();
    $this->putJson("/api/v1/slot-templates/{$saturday['id']}", ['branch_id' => 9])->assertJsonValidationErrors('branch_id');

    // The booked Sunday slot stays at 16:00 beside the new 17:00 one.
    expect(slotsOf($this->shop))->toBe(['2026-12-05 09:00 0/4', '2026-12-06 16:00 1/3', '2026-12-06 17:00 0/3']);

    $this->deleteJson("/api/v1/slot-templates/{$saturday['id']}")->assertNoContent();
    expect(slotsOf($this->shop))->toBe(['2026-12-06 16:00 1/3', '2026-12-06 17:00 0/3']);
});

it('takes places up to the capacity and none in a closed slot', function () {
    window($this->shop, 0, '16:00', '19:00', 2);
    slotsOf($this->shop);
    $slot = Slot::query()->sole();
    $reserve = app(ReserveSlot::class);

    $reserve->handle($slot);
    expect($reserve->handle($slot)->booked)->toBe(2)
        ->and(fn () => $reserve->handle($slot))->toThrow(ApiException::class);
    expect($reserve->release($slot)->booked)->toBe(1);

    $this->putJson("/api/v1/slots/{$slot->id}", ['capacity' => 0])->assertJsonValidationErrors('capacity');
    $this->putJson("/api/v1/slots/{$slot->id}", ['capacity' => 5, 'is_closed' => true])
        ->assertOk()
        ->assertJsonPath('data.remaining', 0)
        ->assertJsonPath('data.booked', 1);
    expect(fn () => $reserve->handle($slot))->toThrow(ApiException::class);
});

it('keeps windows to the branches a user works in and to those allowed', function () {
    $other = Branch::factory()->for($this->shop->organization)->create(['code' => 'S9', 'type' => BranchType::Store]);

    $this->postJson('/api/v1/slot-templates', ['branch_id' => $other->id, 'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '10:00', 'capacity' => 1])->assertNotFound();
    $this->getJson("/api/v1/slots?branch_id={$other->id}")->assertNotFound();
    $this->getJson('/api/v1/slots')->assertJsonValidationErrors('branch_id');

    $this->actingAs(staff($this->shop));
    $this->getJson('/api/v1/slot-templates')->assertForbidden();
    $this->postJson('/api/v1/slot-templates', ['branch_id' => $this->shop->branch->id, 'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '10:00', 'capacity' => 1])->assertForbidden();
});
