<?php

declare(strict_types=1);

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Support\Settings;
use App\Modules\Organization\Tenancy\OrganizationContext;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-24 10:00', 'Asia/Tehran'));
});

const PERSON_CODE = '0013542419';
const LEGAL_ID = '10101234565';

function buyer(array $overrides = []): array
{
    return [
        'buyer_type' => 'legal',
        'buyer_name' => 'هتل نمونه',
        'buyer_national_id' => '۱۰۱۰۱۲۳۴۵۶۵',
        'buyer_economic_code' => '411111111111',
        'buyer_postal_code' => '1234567890',
        'buyer_address' => 'تهران، خیابان ولیعصر',
        ...$overrides,
    ];
}

function issueInvoice(array $orderIds, array $overrides = []): TestResponse
{
    return test()->postJson('/api/v1/invoices', ['order_ids' => $orderIds, ...buyer($overrides)]);
}

function withSettings(object $shop, array $values): void
{
    app(OrganizationContext::class)->runFor($shop->organization->id, fn () => app(Settings::class)->put($values));
}

it('issues numbered invoices with the seller from settings and spreads discount and tax over the lines', function () {
    $shop = shop();
    withSettings($shop, ['invoice.seller_economic_code' => '411234567890', 'invoice.seller_national_id' => LEGAL_ID, 'invoice.notes' => 'با سپاس']);
    $this->actingAs(staff($shop));
    // 3 silk shirts 3,510,000 + suit 3,200,000 + 2.5 kg 2,000,000 = 8,710,000; −710,000; +10% VAT = 8,800,000.
    $order = $this->postJson('/api/v1/orders', orderPayload($shop, ['discount' => ['type' => 'amount', 'value' => 710_000, 'reason' => 'آشنا']]))->assertCreated()->json('data');
    expect($order['total'])->toBe(8_800_000);

    $this->actingAs($this->userWithRole('accountant', $shop->organization));
    $invoice = issueInvoice([$order['id']])->assertCreated()->json('data');

    expect($invoice['number'])->toBe(1)
        ->and($invoice['status'])->toBe('issued')
        ->and($invoice['seller'])->toMatchArray(['name' => $shop->organization->name, 'economic_code' => '411234567890', 'national_id' => LEGAL_ID])
        ->and($invoice['buyer'])->toMatchArray(['type' => 'legal', 'name' => 'هتل نمونه', 'national_id' => LEGAL_ID])
        ->and($invoice['notes'])->toBe('با سپاس')
        ->and($invoice['vat_percent'])->toEqual(10)
        ->and([$invoice['subtotal'], $invoice['discount_amount'], $invoice['tax_amount'], $invoice['total']])->toBe([8_710_000, 710_000, 800_000, 8_800_000])
        ->and($invoice['orders'])->toBe([['id' => $order['id'], 'ticket_number' => $order['ticket_number']]])
        ->and($invoice['lines'])->toHaveCount(3)
        ->and($invoice['lines'][0])->toMatchArray(['position' => 1, 'description' => 'پیراهن (خشکشویی)', 'quantity' => 3, 'unit' => 'piece', 'unit_price' => 1_170_000, 'amount' => 3_510_000, 'discount' => 286_119, 'tax' => 322_388, 'total' => 3_546_269]);

    foreach ($invoice['lines'] as $line) {
        expect($line['amount'] - $line['discount'] + $line['tax'])->toBe($line['total']);
    }
    expect(array_sum(array_column($invoice['lines'], 'total')))->toBe(8_800_000)
        ->and(array_sum(array_column($invoice['lines'], 'tax')))->toBe(800_000);

    // The order shows its invoice, and the buyer is offered again for this customer.
    $this->getJson("/api/v1/orders/{$order['id']}")->assertJsonPath('data.invoice', ['id' => $invoice['id'], 'number' => 1]);
    expect(Customer::query()->find($shop->customer->id)->invoice_details)->toMatchArray(['type' => 'legal', 'national_id' => LEGAL_ID]);
    expect(Activity::query()->where('log_name', 'invoice')->where('event', 'issued')->count())->toBe(1);

    $this->actingAs(staff($shop));
    $next = shirtOrder($shop);
    $this->actingAs($this->userWithRole('accountant', $shop->organization));
    $second = issueInvoice([$next['id']], ['buyer_type' => 'individual', 'buyer_name' => 'مشتری', 'buyer_national_id' => PERSON_CODE])->assertCreated();
    expect($second->json('data.number'))->toBe(2);
});

it('splits prices that include VAT into amount and tax and bills the delivery fee as its own row', function () {
    $shop = shop();
    withSettings($shop, ['tax.prices_include_vat' => true]);
    $this->actingAs(staff($shop, 'branch_manager'));
    $order = shirtOrder($shop);
    // 900,000 including 10% VAT: 818,182 + 81,818. A delivery fee of 50,000 on top.
    Order::query()->whereKey($order['id'])->update(['delivery_fee' => 50_000, 'total' => 950_000]);

    $invoice = issueInvoice([$order['id']], ['buyer_type' => 'individual', 'buyer_national_id' => null, 'buyer_economic_code' => null])->assertCreated()->json('data');

    expect($invoice['lines'])->toHaveCount(2)
        ->and($invoice['lines'][0])->toMatchArray(['amount' => 818_182, 'discount' => 0, 'tax' => 81_818, 'total' => 900_000])
        ->and($invoice['lines'][1])->toMatchArray(['description' => 'هزینه جمع‌آوری و ارسال', 'unit' => null, 'amount' => 50_000, 'tax' => 0, 'total' => 50_000])
        ->and([$invoice['subtotal'], $invoice['tax_amount'], $invoice['total']])->toBe([868_182, 81_818, 950_000]);
});

it('bills an order once, keeps it unchanged until the invoice is voided and never reuses a number', function () {
    $shop = shop();
    $this->actingAs(staff($shop));
    $order = shirtOrder($shop);

    $this->actingAs($this->userWithRole('accountant', $shop->organization));
    $invoice = issueInvoice([$order['id']])->assertCreated()->json('data');
    issueInvoice([$order['id']])->assertConflict()->assertJsonPath('code', 'order_already_invoiced');

    $this->actingAs(staff($shop, 'branch_manager'));
    $this->postJson("/api/v1/orders/{$order['id']}/transitions", ['to' => 'cancelled', 'reason' => 'اشتباه'])
        ->assertConflict()
        ->assertJsonPath('code', 'order_invoiced');
    $this->patchJson("/api/v1/orders/{$order['id']}", [...orderPayload($shop), 'version' => $order['version'], 'reason' => 'اصلاح'])
        ->assertConflict()
        ->assertJsonPath('code', 'order_invoiced');

    // Branch managers issue but do not void.
    $this->postJson("/api/v1/invoices/{$invoice['id']}/void", ['reason' => 'اشتباه در نام'])->assertForbidden();

    $this->actingAs($this->userWithRole('accountant', $shop->organization));
    $this->postJson("/api/v1/invoices/{$invoice['id']}/void", [])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $voided = $this->postJson("/api/v1/invoices/{$invoice['id']}/void", ['reason' => 'اشتباه در نام'])->assertOk()->json('data');
    expect($voided['status'])->toBe('void')
        ->and($voided['void']['reason'])->toBe('اشتباه در نام')
        ->and($voided['void']['by']['id'])->toBe(auth()->id());
    $this->postJson("/api/v1/invoices/{$invoice['id']}/void", ['reason' => 'دوباره'])->assertConflict()->assertJsonPath('code', 'invoice_already_void');

    $this->getJson("/api/v1/orders/{$order['id']}")->assertJsonPath('data.invoice', null);
    expect(issueInvoice([$order['id']])->assertCreated()->json('data.number'))->toBe(2);
});

it('issues one invoice for several orders of a customer', function () {
    $shop = shop();
    $this->actingAs(staff($shop, 'branch_manager'));
    $first = shirtOrder($shop);
    $second = shirtOrder($shop, 2);

    $invoice = issueInvoice([$first['id'], $second['id']])->assertCreated()->json('data');
    expect($invoice['total'])->toBe(990_000 + 1_980_000)
        ->and(array_column($invoice['lines'], 'order_id'))->toBe([$first['id'], $second['id']])
        ->and($invoice['orders'])->toHaveCount(2);

    $other = Customer::factory()->for($shop->organization)->create();
    $third = $this->postJson('/api/v1/orders', orderPayload($shop, ['customer_id' => $other->id]))->assertCreated()->json('data');
    $fourth = shirtOrder($shop);
    issueInvoice([$third['id'], $fourth['id']])->assertConflict()->assertJsonPath('code', 'invoice_customers_differ');

    Order::query()->whereKey($fourth['id'])->update(['status' => 'cancelled']);
    issueInvoice([$fourth['id']])->assertConflict()->assertJsonPath('code', 'order_not_invoiceable');
});

it('checks the buyer national id and economic code', function () {
    $shop = shop();
    $this->actingAs(staff($shop, 'branch_manager'));
    $order = shirtOrder($shop);

    issueInvoice([$order['id']], ['buyer_national_id' => null])->assertUnprocessable()->assertJsonValidationErrors('buyer_national_id');
    issueInvoice([$order['id']], ['buyer_national_id' => '10101234566'])->assertUnprocessable()->assertJsonValidationErrors('buyer_national_id');
    issueInvoice([$order['id']], ['buyer_type' => 'individual', 'buyer_national_id' => '0013542418'])->assertUnprocessable()->assertJsonValidationErrors('buyer_national_id');
    issueInvoice([$order['id']], ['buyer_economic_code' => '12', 'buyer_postal_code' => '123'])->assertUnprocessable()->assertJsonValidationErrors(['buyer_economic_code', 'buyer_postal_code']);
    issueInvoice([$order['id']], ['buyer_type' => 'individual', 'buyer_national_id' => '۰۰۱۳۵۴۲۴۱۹'])->assertCreated()->assertJsonPath('data.buyer.national_id', PERSON_CODE);
});

it('keeps invoices to their permissions and branches', function () {
    $shop = shop();
    $this->actingAs(staff($shop));
    $order = shirtOrder($shop);

    // Cashiers neither issue nor see invoices.
    issueInvoice([$order['id']])->assertForbidden();
    $this->getJson('/api/v1/invoices')->assertForbidden();

    $this->actingAs(staff($shop, 'branch_manager'));
    $invoice = issueInvoice([$order['id']])->assertCreated()->json('data');

    $elsewhere = Branch::factory()->for($shop->organization)->create();
    $this->actingAs($this->userWithRole('branch_manager', $shop->organization, [$elsewhere]));
    $this->getJson("/api/v1/invoices/{$invoice['id']}")->assertNotFound();
    $this->getJson('/api/v1/invoices')->assertOk()->assertJsonCount(0, 'data');
    $this->postJson('/api/v1/invoices', ['order_ids' => [$order['id']], ...buyer()])->assertNotFound();

    $this->actingAs($this->userWithRole('accountant', $shop->organization));
    $this->getJson('/api/v1/invoices?filter[q]=۱')->assertOk()->assertJsonPath('data.0.number', 1)->assertJsonMissingPath('data.0.lines');
    $this->getJson('/api/v1/invoices?filter[q]=هتل')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/invoices?filter[status]=void')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/invoices?filter[from]=2026-11-25')->assertJsonCount(0, 'data');
});
