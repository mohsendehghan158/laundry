<?php

declare(strict_types=1);

use App\Modules\Orders\Models\Order;
use App\Modules\Organization\Models\Branch;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-24 10:00', 'Asia/Tehran'));
});

/** A ready order of two shirts (1,800,000 + 10% VAT), stored on rack A-12. */
function readyOrder(object $shop): array
{
    $order = test()->postJson('/api/v1/orders', orderPayload($shop, ['lines' => [
        ['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'quantity' => 2],
    ]]))->assertCreated()->json('data');

    return test()->postJson("/api/v1/orders/{$order['id']}/transitions", ['to' => 'ready', 'rack_location' => 'a-۱۲'])->assertOk()->json('data');
}

function pieceIds(array $order): array
{
    return collect($order['lines'])->flatMap(fn (array $line) => collect($line['pieces'])->pluck('id'))->all();
}

it('finds an order by ticket number or by a piece tag, typed any way', function () {
    $shop = shop();
    $this->actingAs(staff($shop));
    $order = readyOrder($shop);

    expect($order['rack_location'])->toBe('A-12');

    $this->getJson('/api/v1/orders/lookup?code=t1-۰۰۰۰۱')
        ->assertOk()
        ->assertJsonPath('data.id', $order['id'])
        ->assertJsonPath('matched_piece_id', null);

    $this->getJson('/api/v1/orders/lookup?code=T1-00001-02')
        ->assertOk()
        ->assertJsonPath('data.ticket_number', 'T1-00001')
        ->assertJsonPath('matched_piece_id', pieceIds($order)[1]);

    $this->getJson('/api/v1/orders/lookup?code=T9-99999')->assertNotFound();
    $this->getJson('/api/v1/orders?filter[q]=a-12')->assertJsonPath('meta.total', 1);
});

it('does not find orders of branches the user cannot work in', function () {
    $shop = shop();
    $other = Branch::factory()->for($shop->organization)->create(['code' => 'Z1']);
    Order::factory()->create(['organization_id' => $shop->organization->id, 'branch_id' => $other->id, 'ticket_number' => 'Z1-00001']);
    $this->actingAs(staff($shop));

    $this->getJson('/api/v1/orders/lookup?code=Z1-00001')->assertNotFound();
});

it('refuses handover until the order is ready and paid', function () {
    $shop = shop();
    $this->actingAs(staff($shop));
    $received = $this->postJson('/api/v1/orders', orderPayload($shop))->json('data');

    $this->postJson("/api/v1/orders/{$received['id']}/handover", ['piece_ids' => pieceIds($received)])
        ->assertConflict()
        ->assertJsonPath('code', 'handover_not_ready');

    $order = readyOrder($shop);
    $this->postJson("/api/v1/orders/{$order['id']}/handover", ['piece_ids' => pieceIds($order)])
        ->assertConflict()
        ->assertJsonPath('code', 'handover_unpaid');
});

it('hands over part of an order, then the rest, completing it', function () {
    $shop = shop();
    $this->actingAs(staff($shop));
    $order = readyOrder($shop);
    $this->postJson("/api/v1/orders/{$order['id']}/payments", ['method' => 'card', 'amount' => $order['total']])->assertCreated();
    [$first, $second] = pieceIds($order);

    $this->postJson("/api/v1/orders/{$order['id']}/handover", ['piece_ids' => [$first]])
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.lines.0.pieces.0.status', 'handed_over')
        ->assertJsonPath('data.lines.0.pieces.1.status', 'ready');

    $this->postJson("/api/v1/orders/{$order['id']}/handover", ['piece_ids' => [$first]])
        ->assertConflict()
        ->assertJsonPath('code', 'handover_piece_unavailable');

    $this->postJson("/api/v1/orders/{$order['id']}/handover", ['piece_ids' => [$second]])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.history.*.to_status', ['received', 'ready', 'completed']);

    expect(Order::query()->find($order['id'])->completed_at)->not->toBeNull()
        ->and(Activity::query()->where('event', 'handed_over')->count())->toBe(2);
});

it('refuses pieces of another order', function () {
    $shop = shop();
    $this->actingAs(staff($shop));
    $order = readyOrder($shop);
    $other = readyOrder($shop);
    $this->postJson("/api/v1/orders/{$order['id']}/payments", ['method' => 'card', 'amount' => $order['total']])->assertCreated();

    $this->postJson("/api/v1/orders/{$order['id']}/handover", ['piece_ids' => [pieceIds($other)[0]]])
        ->assertConflict()
        ->assertJsonPath('code', 'handover_piece_unavailable');
});

it('lets a branch manager release an unpaid order on credit', function () {
    $shop = shop();
    $this->actingAs(staff($shop, 'branch_manager'));
    $order = readyOrder($shop);

    $this->postJson("/api/v1/orders/{$order['id']}/handover", ['piece_ids' => pieceIds($order)])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.balance', $order['total']);

    expect(Activity::query()->where('event', 'handed_over')->sole()->properties['balance'])->toBe($order['total']);
});

it('updates the rack location and serves receipt settings to the counter', function () {
    $shop = shop();
    $this->actingAs(staff($shop));
    $order = readyOrder($shop);

    $this->putJson("/api/v1/orders/{$order['id']}/rack", ['rack_location' => 'b۳'])->assertOk()->assertJsonPath('data.rack_location', 'B3');
    $this->putJson("/api/v1/orders/{$order['id']}/rack", ['rack_location' => null])->assertOk()->assertJsonPath('data.rack_location', null);

    $this->getJson('/api/v1/receipt-settings')
        ->assertOk()
        ->assertJsonPath('data.organization_name', $shop->organization->name)
        ->assertJsonPath('data.currency_unit', 'toman');

    $this->actingAs(staff($shop, 'plant_operator'));
    $this->postJson("/api/v1/orders/{$order['id']}/handover", ['piece_ids' => pieceIds($order)])->assertForbidden();
});
