<?php

declare(strict_types=1);

use App\Modules\Orders\Models\Piece;
use Illuminate\Support\Carbon;

it('reports cycle time, on-time delivery and work by station and person', function () {
    $shop = shop();
    $work = workshop($shop);
    $owner = $this->owner($shop->organization);
    $this->actingAs($owner);
    $at = fn (string $moment) => Carbon::setTestNow(Carbon::parse($moment, 'Asia/Tehran'));

    // Two orders taken on Sunday morning, due 72 working hours later.
    $at('2026-11-22 09:00');
    $quick = shirtOrder($shop);
    $slow = test()->postJson('/api/v1/orders', orderPayload($shop, ['lines' => [['item_id' => $shop->suit->id, 'service_type_id' => $shop->dc->id, 'quantity' => 1], ['item_id' => $shop->laundry->id, 'service_type_id' => $shop->ws->id, 'quantity' => 2]]]))->json('data');

    $at('2026-11-22 12:00');
    scan($work->wash, [$quick['lines'][0]['pieces'][0]['tag_code'], $slow['lines'][0]['pieces'][0]['tag_code']]);
    $at('2026-11-22 13:00');
    scan($work->qc, [$quick['lines'][0]['pieces'][0]['tag_code']], ['passed' => true]);
    scan($work->qc, [$slow['lines'][0]['pieces'][0]['tag_code']], ['passed' => false, 'reason' => 'لکه']);

    // The shirt is ready the next day (on time); the suit long after its due time.
    // Back at the counter (as a transfer would bring them).
    Piece::query()->whereIn('order_id', [$quick['id'], $slow['id']])->update(['branch_id' => $shop->branch->id, 'flag' => null]);
    $at('2026-11-23 10:00');
    test()->postJson("/api/v1/assembly/{$quick['id']}/complete", ['piece_ids' => [$quick['lines'][0]['pieces'][0]['id']], 'rack_location' => 'A'])->assertOk();
    $at('2026-12-01 10:00');
    assembleAll($slow, ['rack_location' => 'B']);
    $at('2026-12-01 18:00');
    test()->postJson("/api/v1/orders/{$quick['id']}/payments", ['method' => 'card', 'amount' => $quick['total']])->assertCreated();
    test()->postJson("/api/v1/orders/{$quick['id']}/handover", ['piece_ids' => [$quick['lines'][0]['pieces'][0]['id']]])->assertOk();

    $report = $this->getJson('/api/v1/reports/production?from=2026-11-22&to=2026-12-02')->assertOk()->json('data');

    expect($report['summary'])->toMatchArray(['ready' => 2, 'on_time' => 1, 'late' => 1, 'on_time_percent' => 50.0, 'avg_cycle_hours' => 121.0, 'picked_up' => 1, 'avg_pickup_wait_hours' => 200.0])
        ->and($report['by_day'])->toEqual([
            ['date' => '2026-11-23', 'ready' => 1, 'on_time' => 1, 'avg_cycle_hours' => 25.0],
            ['date' => '2026-12-01', 'ready' => 1, 'on_time' => 0, 'avg_cycle_hours' => 217.0],
        ])
        ->and($report['by_service'])->toEqual([
            ['service' => 'خشکشویی', 'orders' => 2, 'on_time' => 1, 'avg_cycle_hours' => 121.0],
            ['service' => 'شست‌وشو', 'orders' => 1, 'on_time' => 0, 'avg_cycle_hours' => 217.0],
        ])
        ->and($report['stations'])->toBe([
            ['id' => $work->wash->id, 'station' => 'شست‌وشو', 'branch' => 'کارگاه', 'scans' => 2, 'pieces' => 2, 'qc_passed' => 0, 'qc_failed' => 0],
            ['id' => $work->qc->id, 'station' => 'کنترل کیفیت', 'branch' => 'کارگاه', 'scans' => 2, 'pieces' => 2, 'qc_passed' => 1, 'qc_failed' => 1],
        ])
        ->and($report['operators'][0])->toMatchArray(['name' => $owner->name, 'pieces' => 4])
        ->and($report['late_orders'][0])->toMatchArray(['ticket_number' => $slow['ticket_number'], 'customer_name' => 'مشتری نمونه']);
    expect($report['late_orders'][0]['hours_late'])->toBeGreaterThan(0);

    // Only the period counts.
    $this->getJson('/api/v1/reports/production?from=2026-11-22&to=2026-11-30')->assertJsonPath('data.summary.ready', 1)->assertJsonPath('data.summary.on_time_percent', 100);
});

it('shows the production report to the workshop and managers only', function () {
    $shop = shop();
    $work = workshop($shop);

    $this->actingAs($this->userWithRole('plant_operator', $shop->organization, [$work->plant]));
    $this->getJson('/api/v1/reports/production?from=2026-11-01&to=2026-11-30')->assertOk()->assertJsonPath('data.summary.ready', 0)->assertJsonPath('data.summary.on_time_percent', null);

    // The plant counts the counter's orders it worked on.
    Carbon::setTestNow(Carbon::parse('2026-11-22 09:00', 'Asia/Tehran'));
    $this->actingAs($this->owner($shop->organization));
    $worked = shirtOrder($shop);
    $untouched = shirtOrder($shop);
    scan($work->wash, [$worked['lines'][0]['pieces'][0]['tag_code']]);
    Piece::query()->where('order_id', $worked['id'])->update(['branch_id' => $shop->branch->id]);
    Carbon::setTestNow(Carbon::parse('2026-11-23 09:00', 'Asia/Tehran'));
    assembleAll($worked);
    assembleAll($untouched);
    $this->actingAs($this->userWithRole('plant_operator', $shop->organization, [$work->plant]));
    $this->getJson('/api/v1/reports/production?from=2026-11-01&to=2026-11-30')->assertJsonPath('data.summary.ready', 1);

    $this->actingAs(staff($shop));
    $this->getJson('/api/v1/reports/production?from=2026-11-01&to=2026-11-30')->assertForbidden();
    $this->actingAs(staff($shop, 'branch_manager'));
    $this->getJson('/api/v1/reports/production?from=2026-11-30&to=2026-11-01')->assertJsonValidationErrors('to');
});
