<?php

declare(strict_types=1);

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-24 10:00', 'Asia/Tehran'));
});

function incomingNote(object $shop, object $hotel, array $overrides = []): array
{
    return [
        'type' => 'in',
        'company_id' => $hotel->company->id,
        'branch_id' => $shop->branch->id,
        'customer_id' => $shop->customer->id,
        'company_department_id' => $hotel->housekeeping->id,
        'lines' => [
            // The hotel says 20 shirts and 3 suits; 19 shirts are counted here.
            ['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'expected_quantity' => 20, 'counted_quantity' => '۱۹'],
            ['item_id' => $shop->suit->id, 'service_type_id' => $shop->dc->id, 'expected_quantity' => 3, 'counted_quantity' => 3],
        ],
        ...$overrides,
    ];
}

it('turns a confirmed incoming delivery note into an order of what was counted', function () {
    $shop = shop();
    $hotel = hotel($shop, ['credit_limit' => null]);
    $this->actingAs(staff($shop));

    $note = $this->postJson('/api/v1/delivery-notes', incomingNote($shop, $hotel))->assertCreated()->json('data');
    expect($note)->toMatchArray(['number' => 1, 'type' => 'in', 'status' => 'draft', 'order' => null, 'differences' => 1])
        ->and($note['lines'][0])->toMatchArray(['item_name' => 'پیراهن', 'expected_quantity' => 20, 'counted_quantity' => 19, 'difference' => -1])
        ->and($note['department']['name'])->toBe('خانه‌داری');

    $this->putJson("/api/v1/delivery-notes/{$note['id']}", ['lines' => [incomingNote($shop, $hotel)['lines'][0]], 'branch_id' => $shop->branch->id, 'customer_id' => $shop->customer->id])
        ->assertOk()
        ->assertJsonCount(1, 'data.lines');
    $this->postJson("/api/v1/delivery-notes/{$note['id']}/confirm", [])->assertUnprocessable()->assertJsonValidationErrors('counterpart_name');

    $confirmed = $this->postJson("/api/v1/delivery-notes/{$note['id']}/confirm", ['counterpart_name' => 'خانم احمدی'])->assertOk()->json('data');
    expect($confirmed)->toMatchArray(['status' => 'confirmed', 'counterpart_name' => 'خانم احمدی']);

    $order = Order::query()->findOrFail($confirmed['order']['id']);
    expect($order->company_id)->toBe($hotel->company->id)
        ->and($order->company_department_id)->toBe($hotel->housekeeping->id)
        ->and((float) $order->lines()->sole()->quantity)->toBe(19.0)
        ->and($order->notes)->toBe('از حواله ورود شماره 1');

    $this->putJson("/api/v1/delivery-notes/{$note['id']}", ['notes' => 'x', 'branch_id' => $shop->branch->id, 'customer_id' => $shop->customer->id, 'lines' => incomingNote($shop, $hotel)['lines']])
        ->assertConflict()
        ->assertJsonPath('code', 'delivery_note_confirmed');
    $this->deleteJson("/api/v1/delivery-notes/{$note['id']}")->assertConflict();
    $this->getJson("/api/v1/delivery-notes?filter[company_id]={$hotel->company->id}")->assertJsonCount(1, 'data');
});

it('hands an order back with an outgoing note and keeps the company\'s count', function () {
    $shop = shop();
    $hotel = hotel($shop, ['credit_limit' => 50_000_000]);
    $this->actingAs(staff($shop));
    $order = $this->postJson('/api/v1/orders', orderPayload($shop, ['lines' => [['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'quantity' => 4]]]))->assertCreated()->json('data');
    $lineId = $order['lines'][0]['id'];

    $note = $this->postJson('/api/v1/delivery-notes', ['type' => 'out', 'company_id' => $hotel->company->id, 'order_id' => $order['id'], 'lines' => [['order_line_id' => $lineId, 'counted_quantity' => 4]]])
        ->assertCreated()
        ->json('data');
    expect($note['order']['id'])->toBe($order['id'])->and($note['lines'][0]['expected_quantity'])->toEqual(4);

    // Not ready yet.
    $this->postJson("/api/v1/delivery-notes/{$note['id']}/confirm", ['counterpart_name' => 'آقای رضایی'])->assertConflict()->assertJsonPath('code', 'handover_not_ready');

    assembleAll($order);
    $done = $this->postJson("/api/v1/delivery-notes/{$note['id']}/confirm", [
        'counterpart_name' => 'آقای رضایی',
        'received' => [['line_id' => $note['lines'][0]['id'], 'quantity' => 3]],
    ])->assertOk()->json('data');
    expect($done['lines'][0])->toMatchArray(['counted_quantity' => 3, 'difference' => -1])
        ->and($done['differences'])->toBe(1);
    $this->getJson("/api/v1/orders/{$order['id']}")->assertJsonPath('data.status', 'completed');
});

it('only takes delivery notes for the company\'s own customers and orders', function () {
    $shop = shop();
    $hotel = hotel($shop, ['credit_limit' => null]);
    $stranger = Customer::factory()->for($shop->organization)->create();
    $this->actingAs(staff($shop));

    $this->postJson('/api/v1/delivery-notes', incomingNote($shop, $hotel, ['customer_id' => $stranger->id]))->assertUnprocessable()->assertJsonValidationErrors('customer_id');
    $walkIn = $this->postJson('/api/v1/orders', orderPayload($shop, ['customer_id' => $stranger->id]))->assertCreated()->json('data');
    $this->postJson('/api/v1/delivery-notes', ['type' => 'out', 'company_id' => $hotel->company->id, 'order_id' => $walkIn['id']])->assertUnprocessable()->assertJsonValidationErrors('order_id');

    $draft = $this->postJson('/api/v1/delivery-notes', incomingNote($shop, $hotel, ['lines' => [['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'expected_quantity' => 2, 'counted_quantity' => 0]]]))->assertCreated()->json('data');
    $this->postJson("/api/v1/delivery-notes/{$draft['id']}/confirm", ['counterpart_name' => 'x'])->assertConflict()->assertJsonPath('code', 'delivery_note_empty');
    $this->deleteJson("/api/v1/delivery-notes/{$draft['id']}")->assertNoContent();

    $this->actingAs($this->userWithRole('driver', $shop->organization));
    $this->getJson('/api/v1/delivery-notes')->assertForbidden();
});
