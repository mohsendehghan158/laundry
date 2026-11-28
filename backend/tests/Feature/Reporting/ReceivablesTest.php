<?php

declare(strict_types=1);

use App\Modules\Customers\Models\Customer;
use Illuminate\Support\Carbon;

function at(string $moment): void
{
    Carbon::setTestNow(Carbon::parse($moment, 'Asia/Tehran'));
}

it('ages what customers and companies owe by days past due', function () {
    $shop = shop();
    $hotel = hotel($shop, ['credit_limit' => 100_000_000, 'payment_terms_days' => 30]);
    $walkIn = Customer::factory()->for($shop->organization)->create(['name' => 'رهگذر', 'mobile' => '09350001122']);
    $this->actingAs($this->owner($shop->organization));

    // Hotel (30 days' terms): taken in 100 and 50 days before "today".
    at('2026-08-01 10:00');
    $this->postJson('/api/v1/orders', shirtPayload($shop))->assertCreated();
    at('2026-09-10 10:00');
    $this->postJson('/api/v1/orders', shirtPayload($shop, 2))->assertCreated();
    // Walk-in: due at once; 10 days ago, half paid; and a paid one that drops out.
    at('2026-10-30 10:00');
    $part = $this->postJson('/api/v1/orders', shirtPayload($shop, 1, ['customer_id' => $walkIn->id]))->json('data');
    $this->postJson("/api/v1/orders/{$part['id']}/payments", ['method' => 'card', 'amount' => 490_000])->assertCreated();
    $paid = $this->postJson('/api/v1/orders', shirtPayload($shop, 1, ['customer_id' => $walkIn->id]))->json('data');
    $this->postJson("/api/v1/orders/{$paid['id']}/payments", ['method' => 'card', 'amount' => 990_000])->assertCreated();

    at('2026-11-09 12:00');
    $aging = $this->getJson('/api/v1/reports/receivables')->assertOk()->json('data');

    expect($aging['as_of'])->toBe('2026-11-09')
        ->and($aging['rows'])->toHaveCount(2)
        // Due 2026-08-31 (70 days late) and 2026-10-10 (30 days late).
        ->and($aging['rows'][0])->toMatchArray(['kind' => 'company', 'name' => 'هتل آزادی', 'orders' => 2, 'd30' => 1_980_000, 'd90' => 990_000, 'total' => 2_970_000, 'oldest_days' => 70])
        ->and($aging['rows'][1])->toMatchArray(['kind' => 'customer', 'name' => 'رهگذر', 'mobile' => '09350001122', 'orders' => 1, 'd30' => 500_000, 'total' => 500_000, 'oldest_days' => 10])
        ->and($aging['totals'])->toBe(['orders' => 3, 'current' => 0, 'd30' => 2_480_000, 'd60' => 0, 'd90' => 990_000, 'over90' => 0, 'total' => 3_470_000]);

    $sheet = sheetXml($this->get('/api/v1/reports/receivables/export')->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="receivables-2026-11-09.xlsx"')->getContent());
    expect($sheet)->toContain('هتل آزادی')->and($sheet)->toContain('<v>297000</v>');

    $this->actingAs(staff($shop));
    $this->getJson('/api/v1/reports/receivables')->assertForbidden();
});

it('exports a balanced accounting journal of sales, payments and expenses', function () {
    $shop = shop();
    $this->actingAs($this->owner($shop->organization));
    at('2026-11-24 10:00');
    $order = shirtOrder($shop);
    $this->postJson("/api/v1/orders/{$order['id']}/payments", ['method' => 'card', 'amount' => 990_000])->assertCreated();
    $this->postJson("/api/v1/orders/{$order['id']}/refunds", ['method' => 'card', 'amount' => 90_000, 'reason' => 'تأخیر'])->assertCreated();
    $category = $this->getJson('/api/v1/expense-categories')->json('data.0');
    $this->postJson('/api/v1/expenses', ['branch_id' => $shop->branch->id, 'expense_category_id' => $category['id'], 'spent_on' => '2026-11-24', 'amount' => 300_000, 'method' => 'transfer', 'description' => 'اجاره'])->assertCreated();

    $response = $this->get('/api/v1/reports/accounting/export?from=2026-11-01&to=2026-11-30')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="journal-2026-11-01-2026-11-30.xlsx"');
    $sheet = sheetXml($response->getContent());

    expect($sheet)->toContain('حساب‌های دریافتنی')
        ->and($sheet)->toContain('درآمد خدمات')
        ->and($sheet)->toContain('مالیات بر ارزش افزوده پرداختنی')
        ->and($sheet)->toContain('<v>900000</v>')
        ->and($sheet)->toContain('<v>90000</v>')
        // Debits = credits = 990,000 sale + 990,000 payment + 90,000 refund + 300,000 expense.
        ->and(substr_count($sheet, '<v>2370000</v>'))->toBe(2);

    $this->get('/api/v1/reports/accounting/export?from=2026-11-30&to=2026-11-01')->assertUnprocessable();
});
