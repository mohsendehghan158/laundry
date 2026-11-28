<?php

declare(strict_types=1);

use App\Modules\B2B\Models\Company;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Customers\Models\Customer;
use App\Modules\Organization\Tenancy\OrganizationContext;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-24 10:00', 'Asia/Tehran'));
});

it('manages companies with their contract, departments and members', function () {
    $shop = shop();
    $this->actingAs($this->userWithRole('accountant', $shop->organization));

    $this->postJson('/api/v1/companies', ['name' => 'هتل', 'national_id' => '10101234566'])->assertUnprocessable()->assertJsonValidationErrors('national_id');
    $company = $this->postJson('/api/v1/companies', [
        'name' => 'هتل آزادی', 'national_id' => '۱۰۱۰۱۲۳۴۵۶۵', 'economic_code' => '411111111111', 'contact_mobile' => '09121234567',
        'contract_number' => 'Q-1405-12', 'contract_starts_on' => '2026-11-01', 'contract_ends_on' => '2027-10-31',
        'credit_limit' => 50_000_000, 'payment_terms_days' => 15,
    ])->assertCreated()->json('data');
    expect($company)->toMatchArray(['national_id' => '10101234565', 'on_account' => true, 'contract_running' => true, 'outstanding' => 0, 'departments' => [], 'members' => []]);

    $this->postJson("/api/v1/companies/{$company['id']}/departments", ['name' => 'خانه‌داری', 'code' => 'HK'])->assertCreated()->assertJsonPath('data.departments.0.code', 'HK');
    $department = $this->getJson("/api/v1/companies/{$company['id']}")->json('data.departments.0.id');
    $this->putJson("/api/v1/companies/{$company['id']}/departments/{$department}", ['is_active' => false])->assertOk()->assertJsonPath('data.departments.0.is_active', false);

    $this->postJson("/api/v1/companies/{$company['id']}/members", ['customer_id' => $shop->customer->id])->assertOk()->assertJsonPath('data.members.0.id', $shop->customer->id);
    $this->getJson('/api/v1/companies?filter[q]=آزادی')->assertJsonCount(1, 'data');
    $this->deleteJson("/api/v1/companies/{$company['id']}/members/{$shop->customer->id}")->assertOk()->assertJsonPath('data.members', []);

    $this->putJson("/api/v1/companies/{$company['id']}", ['credit_limit' => null, 'contract_ends_on' => '2026-11-20', 'contract_starts_on' => null])
        ->assertOk()
        ->assertJsonPath('data.on_account', false)
        ->assertJsonPath('data.contract_running', false);
    $this->deleteJson("/api/v1/companies/{$company['id']}")->assertNoContent();

    $this->actingAs(staff($shop, 'branch_manager'));
    $this->getJson('/api/v1/companies')->assertOk();
    $this->postJson('/api/v1/companies', ['name' => 'x'])->assertForbidden();
    $this->actingAs(staff($shop));
    $this->getJson('/api/v1/companies')->assertForbidden();
});

it('prices a company customer\'s orders from its contract list while the contract runs', function () {
    $shop = shop();
    $this->actingAs($this->owner($shop->organization));

    // A contract list at 10% off the default prices, with the suit set by hand.
    $list = $this->postJson('/api/v1/price-lists', ['name' => 'قرارداد هتل‌ها', 'percent' => -10])->assertCreated()->json('data');
    expect($list)->toMatchArray(['is_default' => false, 'prices_count' => 3]);
    $this->putJson('/api/v1/prices', ['price_list_id' => $list['id'], 'prices' => [
        ['item_id' => $shop->suit->id, 'service_type_id' => $shop->dc->id, 'price' => 2_500_000],
        ['item_id' => $shop->laundry->id, 'service_type_id' => $shop->ws->id, 'price' => null],
    ]])->assertOk()->assertJsonCount(2, 'data');
    $this->getJson("/api/v1/prices?price_list_id={$list['id']}")->assertJsonPath('data.0.price', 810_000);
    $this->getJson('/api/v1/prices')->assertJsonPath('data.0.price', 900_000);
    $this->getJson('/api/v1/price-lists')->assertJsonPath('data.0.is_default', true)->assertJsonPath('data.1.prices_count', 2);

    $hotel = hotel($shop, ['price_list_id' => $list['id'], 'contract_ends_on' => '2026-12-31', 'credit_limit' => null]);
    $lines = [
        ['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'quantity' => 1],
        ['item_id' => $shop->suit->id, 'service_type_id' => $shop->dc->id, 'quantity' => 1],
        // Not in the contract list any more: the default price.
        ['item_id' => $shop->laundry->id, 'service_type_id' => $shop->ws->id, 'quantity' => 1],
    ];
    $order = $this->postJson('/api/v1/orders', orderPayload($shop, ['lines' => $lines, 'company_department_id' => $hotel->housekeeping->id]))->assertCreated()->json('data');
    expect(array_column($order['lines'], 'unit_price'))->toBe([810_000, 2_500_000, 800_000])
        ->and($order['company'])->toBe(['id' => $hotel->company->id, 'name' => 'هتل آزادی', 'department' => ['id' => $hotel->housekeeping->id, 'name' => 'خانه‌داری']]);
    $this->postJson('/api/v1/orders/price-preview', orderPayload($shop, ['lines' => $lines]))->assertJsonPath('data.lines.0.unit_price', 810_000);

    // After the contract ends, default prices again.
    Carbon::setTestNow(Carbon::parse('2027-01-02 10:00', 'Asia/Tehran'));
    $later = $this->postJson('/api/v1/orders', shirtPayload($shop))->assertCreated()->json('data');
    expect($later['lines'][0]['unit_price'])->toBe(900_000);

    $this->deleteJson('/api/v1/price-lists/'.PriceList::default()->id)->assertConflict()->assertJsonPath('code', 'price_list_default');
    $this->deleteJson("/api/v1/price-lists/{$list['id']}")->assertNoContent();
    expect($hotel->company->refresh()->price_list_id)->toBeNull();
});

it('takes a company\'s orders on account up to its credit limit and hands them over unpaid', function () {
    $shop = shop();
    $hotel = hotel($shop);
    $this->actingAs(staff($shop));

    $this->getJson("/api/v1/customers/{$shop->customer->id}")
        ->assertJsonPath('data.company.on_account', true)
        ->assertJsonPath('data.company.credit_available', 2_000_000)
        ->assertJsonPath('data.company.departments.*.name', ['خانه‌داری', 'رستوران']);

    // 990,000 each; the third would make 2,970,000.
    $first = $this->postJson('/api/v1/orders', shirtPayload($shop))->assertCreated()->json('data');
    $this->postJson('/api/v1/orders', shirtPayload($shop))->assertCreated();
    $this->postJson('/api/v1/orders', shirtPayload($shop))->assertConflict()->assertJsonPath('code', 'credit_limit_exceeded');
    $this->getJson("/api/v1/customers/{$shop->customer->id}")->assertJsonPath('data.company.credit_available', 20_000);

    $this->postJson("/api/v1/orders/{$first['id']}/payments", ['method' => 'card', 'amount' => 990_000])->assertCreated();
    $this->postJson('/api/v1/orders', shirtPayload($shop))->assertCreated();

    // Raising the quantity of an order on account counts against the limit too.
    $third = $this->getJson('/api/v1/orders?filter[company_id]='.$hotel->company->id)->assertJsonCount(3, 'data')->json('data.0');
    $this->patchJson("/api/v1/orders/{$third['id']}", [...shirtPayload($shop, 3), 'version' => $third['version'], 'reason' => 'بیشتر'])
        ->assertConflict()
        ->assertJsonPath('code', 'credit_limit_exceeded');

    // A cashier (no orders.handover_unpaid) releases the company's unpaid order.
    $ready = assembleAll($this->getJson("/api/v1/orders/{$third['id']}")->json('data'));
    $pieces = collect($ready['lines'])->flatMap(fn (array $line) => collect($line['pieces'])->pluck('id'))->all();
    $this->postJson("/api/v1/orders/{$third['id']}/handover", ['piece_ids' => $pieces])->assertOk();

    $this->actingAs($this->userWithRole('accountant', $shop->organization));
    $this->getJson("/api/v1/companies/{$hotel->company->id}")->assertJsonPath('data.outstanding', 1_980_000);
    $this->deleteJson("/api/v1/companies/{$hotel->company->id}")->assertConflict()->assertJsonPath('code', 'company_has_orders');
});

it('charges orders only to the company\'s own active departments', function () {
    $shop = shop();
    $hotel = hotel($shop, ['credit_limit' => null]);
    $other = app(OrganizationContext::class)->runFor($shop->organization->id, fn () => Company::query()->create(['name' => 'رستوران'])->departments()->create(['name' => 'آشپزخانه']));
    $this->actingAs(staff($shop));

    $this->postJson('/api/v1/orders', shirtPayload($shop, 1, ['company_department_id' => $other->id]))->assertUnprocessable()->assertJsonValidationErrors('company_department_id');
    $hotel->restaurant->update(['is_active' => false]);
    $this->postJson('/api/v1/orders', shirtPayload($shop, 1, ['company_department_id' => $hotel->restaurant->id]))->assertUnprocessable();

    // Not on account: an ordinary paying customer, still charged to the company.
    $order = $this->postJson('/api/v1/orders', shirtPayload($shop, 1, ['company_department_id' => $hotel->housekeeping->id]))->assertCreated()->json('data');
    expect($order['company']['department']['name'])->toBe('خانه‌داری');
    $ready = assembleAll($order);
    $pieces = collect($ready['lines'])->flatMap(fn (array $line) => collect($line['pieces'])->pluck('id'))->all();
    $this->postJson("/api/v1/orders/{$order['id']}/handover", ['piece_ids' => $pieces])->assertConflict()->assertJsonPath('code', 'handover_unpaid');

    $walkIn = Customer::factory()->for($shop->organization)->create();
    $this->postJson('/api/v1/orders', shirtPayload($shop, 1, ['customer_id' => $walkIn->id, 'company_department_id' => $hotel->housekeeping->id]))->assertUnprocessable();
    $this->postJson('/api/v1/orders', shirtPayload($shop, 1, ['customer_id' => $walkIn->id]))->assertCreated()->assertJsonPath('data.company', null);
});
