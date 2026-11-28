<?php

declare(strict_types=1);

use App\Modules\Billing\Models\Invoice;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Carbon;

it('builds a company\'s monthly statement from its orders and delivery notes and invoices it in one go', function () {
    $shop = shop();
    $hotel = hotel($shop, ['credit_limit' => 100_000_000, 'national_id' => '10101234565', 'economic_code' => '411111111111']);
    $colleague = Customer::factory()->for($shop->organization)->create(['company_id' => $hotel->company->id]);
    $this->actingAs(staff($shop, 'branch_manager'));
    $at = fn (string $moment) => Carbon::setTestNow(Carbon::parse($moment, 'Asia/Tehran'));

    // October: still owed when November starts.
    $at('2026-10-20 10:00');
    $this->postJson('/api/v1/orders', shirtPayload($shop))->assertCreated();

    // November: an incoming note for housekeeping, a restaurant order by a colleague, and a cancelled one.
    $at('2026-11-03 10:00');
    $note = $this->postJson('/api/v1/delivery-notes', incomingNote($shop, $hotel, ['lines' => [['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'expected_quantity' => 2, 'counted_quantity' => 2]]]))->json('data');
    $this->postJson("/api/v1/delivery-notes/{$note['id']}/confirm", ['counterpart_name' => 'خانم احمدی'])->assertOk();
    $at('2026-11-10 10:00');
    $restaurant = $this->postJson('/api/v1/orders', shirtPayload($shop, 1, ['customer_id' => $colleague->id, 'company_department_id' => $hotel->restaurant->id]))->assertCreated()->json('data');
    $this->postJson("/api/v1/orders/{$restaurant['id']}/payments", ['method' => 'card', 'amount' => 500_000])->assertCreated();
    $cancelled = $this->postJson('/api/v1/orders', shirtPayload($shop))->json('data');
    $this->postJson("/api/v1/orders/{$cancelled['id']}/transitions", ['to' => 'cancelled', 'reason' => 'x'])->assertOk();

    $at('2026-12-02 10:00');
    $statement = $this->getJson("/api/v1/companies/{$hotel->company->id}/statement?from=2026-11-01&to=2026-11-30")->assertOk()->json('data');

    expect($statement['previous_balance'])->toBe(990_000)
        ->and($statement['orders'])->toHaveCount(2)
        ->and($statement['orders'][0])->toMatchArray(['department' => 'خانه‌داری', 'delivery_notes' => [1], 'total' => 1_980_000, 'balance' => 1_980_000, 'invoice' => null])
        ->and($statement['orders'][1])->toMatchArray(['department' => 'رستوران', 'total' => 990_000, 'paid' => 500_000])
        ->and($statement['departments'])->toBe([['name' => 'خانه‌داری', 'orders' => 1, 'total' => 1_980_000], ['name' => 'رستوران', 'orders' => 1, 'total' => 990_000]])
        ->and($statement['delivery_notes'][0])->toMatchArray(['number' => 1, 'type' => 'in', 'items' => 2, 'differences' => 0])
        ->and($statement['totals'])->toMatchArray(['orders' => 2, 'tax' => 270_000, 'total' => 2_970_000, 'paid' => 500_000, 'balance' => 2_470_000]);

    // One click: one invoice for both customers' orders, with the hotel as buyer.
    $this->actingAs($this->userWithRole('accountant', $shop->organization));
    $created = $this->postJson("/api/v1/companies/{$hotel->company->id}/statement/invoice", ['from' => '2026-11-01', 'to' => '2026-11-30'])->assertCreated()->json('data');
    expect($created['total'])->toBe(2_970_000);

    $invoice = Invoice::query()->findOrFail($created['id']);
    expect($invoice->company_id)->toBe($hotel->company->id)
        ->and($invoice->buyer)->toMatchArray(['type' => 'legal', 'name' => 'هتل آزادی', 'national_id' => '10101234565'])
        ->and($invoice->orders()->pluck('orders.id')->all())->toBe(array_column($statement['orders'], 'id'));

    $this->getJson("/api/v1/companies/{$hotel->company->id}/statement?from=2026-11-01&to=2026-11-30")
        ->assertJsonPath('data.orders.0.invoice.number', $invoice->number)
        ->assertJsonPath('data.uninvoiced_order_ids', []);
    $this->postJson("/api/v1/companies/{$hotel->company->id}/statement/invoice", ['from' => '2026-11-01', 'to' => '2026-11-30'])
        ->assertConflict()
        ->assertJsonPath('code', 'statement_nothing_to_invoice');

    // Orders of different customers outside one company still cannot share an invoice.
    $walkIn = Customer::factory()->for($shop->organization)->create();
    $this->actingAs(staff($shop, 'branch_manager'));
    $other = $this->postJson('/api/v1/orders', shirtPayload($shop, 1, ['customer_id' => $walkIn->id]))->json('data');
    $october = Order::query()->where('company_id', $hotel->company->id)->orderBy('id')->firstOrFail();
    $this->postJson('/api/v1/invoices', ['order_ids' => [$october->id, $other['id']], 'buyer_type' => 'individual', 'buyer_name' => 'x'])
        ->assertConflict()
        ->assertJsonPath('code', 'invoice_customers_differ');

    $this->actingAs(staff($shop));
    $this->getJson("/api/v1/companies/{$hotel->company->id}/statement?from=2026-11-01&to=2026-11-30")->assertForbidden();
});
