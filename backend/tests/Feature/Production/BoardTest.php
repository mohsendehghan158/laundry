<?php

declare(strict_types=1);

use App\Modules\Orders\Models\Order;
use App\Modules\Production\Events\BoardChanged;
use App\Modules\Production\Events\OrdersOverdue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->shop = shop();
    $this->work = workshop($this->shop);
    $this->actingAs($this->owner($this->shop->organization));
});

it('shows the pieces in work by station, most urgent orders first', function () {
    $late = shirtOrder($this->shop, 2);
    $express = test()->postJson('/api/v1/orders', orderPayload($this->shop, ['turnaround_tier_id' => $this->shop->express->id, 'lines' => [['item_id' => $this->shop->shirt->id, 'service_type_id' => $this->shop->dc->id, 'quantity' => 1]]]))->json('data');
    $calm = shirtOrder($this->shop);
    Order::query()->whereKey($late['id'])->update(['due_at' => now()->subHour()]);

    scan($this->work->wash, [$late['lines'][0]['pieces'][0]['tag_code'], $express['lines'][0]['pieces'][0]['tag_code'], $calm['lines'][0]['pieces'][0]['tag_code']]);
    scan($this->work->qc, [$calm['lines'][0]['pieces'][0]['tag_code']], ['passed' => false, 'reason' => 'لکه']);

    $board = $this->getJson("/api/v1/production/board?branch_id={$this->work->plant->id}")->assertOk()->json('data');

    expect(array_column($board['columns'], 'key'))->toBe(['waiting', "station-{$this->work->wash->id}", "station-{$this->work->qc->id}", 'assembled'])
        ->and($board['columns'][1]['pieces'])->toBe(2)
        ->and(array_column($board['columns'][1]['orders'], 'ticket_number'))->toBe([$late['ticket_number'], $express['ticket_number']])
        ->and($board['columns'][1]['orders'][0])->toMatchArray(['pieces' => 1, 'total_pieces' => 2, 'is_overdue' => true])
        ->and($board['columns'][2]['orders'][0])->toMatchArray(['ticket_number' => $calm['ticket_number'], 'rework' => 1])
        ->and($board['counters'])->toMatchArray(['pieces' => 3, 'orders' => 3, 'overdue' => 1, 'express' => 1, 'rework' => 1]);

    // The counter's board still has the unscanned shirt of the late order waiting.
    $counter = $this->getJson("/api/v1/production/board?branch_id={$this->shop->branch->id}")->json('data');
    expect($counter['columns'][0]['orders'][0])->toMatchArray(['ticket_number' => $late['ticket_number'], 'pieces' => 1]);
});

it('tells the boards involved to reload after scans and order changes', function () {
    Event::fake([BoardChanged::class]);
    $order = shirtOrder($this->shop);
    Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event): bool => $event->branchIds === [$this->shop->branch->id] && $event->orderId === $order['id']);

    scan($this->work->wash, [$order['lines'][0]['pieces'][0]['tag_code']]);
    Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event): bool => $event->reason === 'scan'
        && $event->branchIds === [$this->work->plant->id, $this->shop->branch->id]
        && array_map(fn ($channel) => $channel->name, $event->broadcastOn()) === ["private-plant.{$this->work->plant->id}.board", "private-plant.{$this->shop->branch->id}.board"]);
});

it('lets only workshop staff of a branch listen to its board', function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb' => ['driver' => 'reverb', 'key' => 'key', 'secret' => 'secret', 'app_id' => '1', 'options' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false]]]);
    // Channels were registered on the test's null broadcaster at boot; register them on this one.
    Broadcast::forgetDrivers();
    require base_path('routes/channels.php');
    $channel = "private-plant.{$this->work->plant->id}.board";

    $this->actingAs($this->userWithRole('plant_operator', $this->shop->organization, [$this->work->plant]));
    $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => $channel])->assertOk()->assertJsonStructure(['auth']);

    $this->actingAs(staff($this->shop, 'branch_manager'));
    $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => $channel])->assertForbidden();
    $this->getJson("/api/v1/production/board?branch_id={$this->work->plant->id}")->assertNotFound();

    $this->actingAs($this->userWithRole('driver', $this->shop->organization, [$this->shop->branch]));
    $this->getJson("/api/v1/production/board?branch_id={$this->shop->branch->id}")->assertForbidden();
});

it('flags orders on the boards once, as they pass their due time', function () {
    Event::fake([OrdersOverdue::class]);
    $order = shirtOrder($this->shop);
    scan($this->work->wash, [$order['lines'][0]['pieces'][0]['tag_code']]);
    $due = Carbon::parse($order['due_at']);

    Carbon::setTestNow($due->copy()->subMinute());
    $this->artisan('production:overdue-alerts')->assertSuccessful();
    Event::assertNotDispatched(OrdersOverdue::class);

    Carbon::setTestNow($due->copy()->addMinute());
    $this->artisan('production:overdue-alerts')->assertSuccessful();
    Event::assertDispatched(OrdersOverdue::class, 2);
    Event::assertDispatched(OrdersOverdue::class, fn (OrdersOverdue $event): bool => $event->branchId === $this->work->plant->id && $event->tickets === [$order['ticket_number']]);

    Carbon::setTestNow($due->copy()->addMinutes(2));
    $this->artisan('production:overdue-alerts')->assertSuccessful();
    Event::assertDispatched(OrdersOverdue::class, 2);
});

it('tells the browser whether live updates are on and where to connect', function () {
    $this->getJson('/api/v1/realtime')->assertOk()->assertJsonPath('data.enabled', false)->assertJsonPath('data.key', null);

    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k1', 'services.realtime' => ['host' => 'ws.example.test', 'port' => 443, 'scheme' => 'https']]);
    $this->getJson('/api/v1/realtime')->assertJsonPath('data', ['enabled' => true, 'key' => 'k1', 'host' => 'ws.example.test', 'port' => 443, 'scheme' => 'https']);
});
