<?php

declare(strict_types=1);

use App\Modules\Production\Models\PieceScan;
use App\Modules\Production\Models\QcInspection;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->shop = shop();
    $this->work = workshop($this->shop);
    $this->cashier = staff($this->shop);
    $this->operator = $this->userWithRole('plant_operator', $this->shop->organization, [$this->work->plant]);
});

it('records a piece reaching a station and starts work on its order', function () {
    $this->actingAs($this->cashier);
    $order = shirtOrder($this->shop, 2);
    [$first, $second] = array_column($order['lines'][0]['pieces'], 'tag_code');

    $this->actingAs($this->operator);
    $result = scan($this->work->wash, [strtolower($first)]);

    expect($result[0])->toMatchArray(['tag' => $first, 'ok' => true, 'duplicate' => false])
        ->and($result[0]['piece'])->toMatchArray(['status' => 'in_process', 'item_name' => 'پیراهن'])
        ->and($result[0]['piece']['order'])->toMatchArray(['ticket_number' => $order['ticket_number'], 'piece_count' => 2, 'is_express' => false])
        // It came to the plant without a transfer.
        ->and($result[0]['warnings'])->toBe(['قطعه بدون بارنامه از «'.$this->shop->branch->name.'» به اینجا آمده است.']);

    $this->actingAs($this->cashier);
    $this->getJson("/api/v1/orders/{$order['id']}")->assertJsonPath('data.status', 'in_process');
    $this->getJson("/api/v1/pieces/{$first}")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_process')
        ->assertJsonPath('data.branch.id', $this->work->plant->id)
        ->assertJsonPath('data.station.name', 'شست‌وشو')
        ->assertJsonPath('data.events.0.event', 'scanned')
        ->assertJsonPath('data.events.0.user_name', $this->operator->name);
    $this->getJson("/api/v1/pieces/{$second}")->assertJsonPath('data.status', 'received')->assertJsonPath('data.events', []);
});

it('does not count a scanner repeating itself, but does count a later scan', function () {
    $this->actingAs($this->cashier);
    $tag = shirtOrder($this->shop)['lines'][0]['pieces'][0]['tag_code'];
    $this->actingAs($this->operator);

    scan($this->work->wash, [$tag]);
    expect(scan($this->work->wash, [$tag])[0]['duplicate'])->toBeTrue()
        ->and(PieceScan::query()->count())->toBe(1);

    Carbon::setTestNow(now()->addMinute());
    expect(scan($this->work->wash, [$tag])[0]['duplicate'])->toBeFalse()
        ->and(PieceScan::query()->count())->toBe(2);

    $this->getJson("/api/v1/stations/{$this->work->wash->id}/activity")
        ->assertJsonPath('data.today', ['mine' => 2, 'total' => 2])
        ->assertJsonPath('data.recent.0.tag_code', $tag);
});

it('handles a batch tag by tag', function () {
    $this->actingAs($this->cashier);
    $tags = array_column(shirtOrder($this->shop, 3)['lines'][0]['pieces'], 'tag_code');
    $this->actingAs($this->operator);

    $results = scan($this->work->wash, [$tags[0], 'NOPE-1', $tags[1], $tags[0], '۱۲']);

    expect(array_column($results, 'ok'))->toBe([true, false, true, false])
        ->and($results[1])->toMatchArray(['tag' => 'NOPE-1', 'code' => 'piece_not_found', 'message' => 'قطعه‌ای با این کد پیدا نشد.'])
        ->and($results[3]['tag'])->toBe('12');
});

it('fails pieces at quality control for rework and passes them once fixed', function () {
    $this->actingAs($this->cashier);
    $tag = shirtOrder($this->shop)['lines'][0]['pieces'][0]['tag_code'];
    $this->actingAs($this->operator);

    $this->postJson('/api/v1/scans', ['station_id' => $this->work->qc->id, 'tags' => [$tag]])->assertJsonValidationErrors('passed');
    $this->postJson('/api/v1/scans', ['station_id' => $this->work->qc->id, 'tags' => [$tag], 'passed' => false])->assertJsonValidationErrors('reason');

    $failed = scan($this->work->qc, [$tag], ['passed' => false, 'reason' => 'لکه باقی مانده', 'defect_type_ids' => [$this->shop->tear->id]]);
    expect($failed[0]['piece'])->toMatchArray(['flag' => 'rework', 'flag_note' => 'لکه باقی مانده، پارگی'])
        ->and(QcInspection::query()->sole())->toMatchArray(['passed' => false, 'reason' => 'لکه باقی مانده', 'defects' => ['پارگی']]);

    expect(scan($this->work->wash, [$tag])[0]['warnings'])->toContain('دوباره‌کاری: لکه باقی مانده، پارگی');

    expect(scan($this->work->qc, [$tag], ['passed' => true])[0]['piece']['flag'])->toBeNull();
    $this->getJson("/api/v1/pieces/{$tag}")->assertJsonPath('data.events.*.event', ['qc_failed', 'scanned', 'qc_passed']);
});

it('refuses pieces that are held, ready or in a closed order', function () {
    $this->actingAs($this->owner($this->shop->organization));
    $held = shirtOrder($this->shop)['lines'][0]['pieces'][0];
    $cancelled = shirtOrder($this->shop);
    $this->postJson("/api/v1/orders/{$cancelled['id']}/transitions", ['to' => 'cancelled', 'reason' => 'x'])->assertOk();

    $this->postJson("/api/v1/pieces/{$held['id']}/hold", ['reason' => 'منتظر تأیید مشتری'])->assertOk()->assertJsonPath('data.flag', 'on_hold');
    $results = scan($this->work->wash, [$held['tag_code'], $cancelled['lines'][0]['pieces'][0]['tag_code']]);
    expect($results[0])->toMatchArray(['ok' => false, 'message' => 'این قطعه نگه داشته شده است: منتظر تأیید مشتری'])
        ->and($results[1]['code'])->toBe('scan_order_closed');

    $this->postJson("/api/v1/pieces/{$held['id']}/release")->assertOk()->assertJsonPath('data.flag', null);
    $this->postJson("/api/v1/pieces/{$held['id']}/release")->assertConflict();
    expect(scan($this->work->wash, [$held['tag_code']])[0]['ok'])->toBeTrue();
});

it('shows a piece only to those who work with its order or where it is', function () {
    $this->actingAs($this->cashier);
    $tag = shirtOrder($this->shop)['lines'][0]['pieces'][0]['tag_code'];

    // The plant operator cannot see the counter's piece until it reaches the plant.
    $this->actingAs($this->operator);
    $this->getJson("/api/v1/pieces/{$tag}")->assertNotFound();
    scan($this->work->wash, [$tag]);
    $this->getJson('/api/v1/pieces/'.str_replace(['T', '1'], ['t', '۱'], $tag))->assertOk()->assertJsonPath('data.order.customer_name', 'مشتری نمونه');

    $this->actingAs($this->userWithRole('driver', $this->shop->organization, [$this->shop->branch]));
    $this->getJson("/api/v1/pieces/{$tag}")->assertForbidden();
    $this->actingAs($this->cashier);
    $this->postJson('/api/v1/scans', ['station_id' => $this->work->press->id, 'tags' => [$tag]])->assertForbidden();
});
