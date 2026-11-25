<?php

declare(strict_types=1);

use App\Modules\Organization\Enums\BranchType;
use App\Modules\Organization\Models\Branch;
use Illuminate\Support\Carbon;

/**
 * Monday: two shirts (one paid by card); Tuesday: a suit, and a shirt that
 * is cancelled. All in Tehran time.
 */
function salesWeek(object $shop): array
{
    $at = fn (string $moment) => Carbon::setTestNow(Carbon::parse($moment, 'Asia/Tehran'));
    $shirt = ['lines' => [['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'quantity' => 1]]];
    $suit = ['lines' => [['item_id' => $shop->suit->id, 'service_type_id' => $shop->dc->id, 'quantity' => 1]]];

    $at('2026-11-23 09:30');
    $first = test()->postJson('/api/v1/orders', orderPayload($shop, $shirt))->json('data');
    test()->postJson("/api/v1/orders/{$first['id']}/payments", ['method' => 'card', 'amount' => $first['total']])->assertCreated();
    // Late evening in Tehran, still Monday there although Tuesday is close in UTC terms.
    $at('2026-11-23 23:50');
    test()->postJson('/api/v1/orders', orderPayload($shop, $shirt))->assertCreated();

    $at('2026-11-24 10:00');
    test()->postJson('/api/v1/orders', orderPayload($shop, $suit))->assertCreated();
    $cancelled = test()->postJson('/api/v1/orders', orderPayload($shop, $shirt))->json('data');
    test()->postJson("/api/v1/orders/{$cancelled['id']}/transitions", ['to' => 'cancelled', 'reason' => 'x'])->assertOk();

    return [$first, $cancelled];
}

function sheetXml(string|false $bytes): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, (string) $bytes);
    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    unlink($path);

    return $sheet;
}

it('summarizes today and the last two weeks for the dashboard', function () {
    $shop = shop();
    $this->actingAs(staff($shop, 'branch_manager'));
    salesWeek($shop);

    $this->getJson('/api/v1/reports/dashboard')
        ->assertOk()
        // Today (Tuesday): the suit only; the cancelled shirt does not count.
        ->assertJsonPath('data.today.orders', 1)
        ->assertJsonPath('data.today.pieces', 2)
        ->assertJsonPath('data.today.sales', 3_520_000)
        ->assertJsonPath('data.today.collected', 0)
        ->assertJsonPath('data.workload.in_process', 3)
        ->assertJsonCount(14, 'data.trend')
        ->assertJsonPath('data.trend.12', ['date' => '2026-11-23', 'orders' => 2, 'sales' => 1_980_000])
        ->assertJsonPath('data.trend.13', ['date' => '2026-11-24', 'orders' => 1, 'sales' => 3_520_000]);
});

it('reports sales by day with money collected, excluding cancelled orders', function () {
    $shop = shop();
    $this->actingAs(staff($shop, 'branch_manager'));
    salesWeek($shop);

    $this->getJson('/api/v1/reports/sales?from=2026-11-22&to=2026-11-24&group=day')
        ->assertOk()
        ->assertJsonPath('data.rows.*.label', ['2026-11-23', '2026-11-24'])
        ->assertJsonPath('data.rows.0.orders', 2)
        ->assertJsonPath('data.rows.0.total', 1_980_000)
        ->assertJsonPath('data.rows.0.collected', 990_000)
        ->assertJsonPath('data.rows.0.tax', 180_000)
        ->assertJsonPath('data.totals', ['orders' => 3, 'quantity' => null, 'lines' => null, 'discount' => 0, 'tax' => 500_000, 'total' => 5_500_000, 'collected' => 990_000]);
});

it('groups sales by item, service, branch and user', function () {
    $shop = shop();
    $manager = staff($shop, 'branch_manager');
    $this->actingAs($manager);
    salesWeek($shop);
    $query = 'from=2026-11-23&to=2026-11-24&group=';

    $this->getJson("/api/v1/reports/sales?{$query}item")
        ->assertJsonPath('data.rows.*.label', ['کت‌وشلوار', 'پیراهن'])
        ->assertJsonPath('data.rows.1.quantity', 2)
        ->assertJsonPath('data.rows.1.total', 1_800_000)
        ->assertJsonPath('data.totals.quantity', 3)
        ->assertJsonPath('data.totals.lines', 5_000_000)
        ->assertJsonPath('data.totals.total', 5_500_000);
    $this->getJson("/api/v1/reports/sales?{$query}service")->assertJsonPath('data.rows.0.label', 'خشکشویی')->assertJsonPath('data.rows.0.orders', 3);
    $this->getJson("/api/v1/reports/sales?{$query}branch")->assertJsonPath('data.rows.0.label', $shop->branch->name);
    $this->getJson("/api/v1/reports/sales?{$query}user")->assertJsonPath('data.rows.0.label', $manager->name)->assertJsonPath('data.rows.0.orders', 3);
});

it('counts only the branches the user works in', function () {
    $shop = shop();
    $other = Branch::factory()->for($shop->organization)->create(['code' => 'T2', 'type' => BranchType::Store]);
    $this->actingAs($this->owner($shop->organization));
    salesWeek($shop);
    Carbon::setTestNow(Carbon::parse('2026-11-24 11:00', 'Asia/Tehran'));
    $this->postJson('/api/v1/orders', orderPayload($shop, ['branch_id' => $other->id]))->assertCreated();

    $this->getJson('/api/v1/reports/sales?from=2026-11-24&to=2026-11-24&group=branch')->assertJsonCount(2, 'data.rows');
    $this->getJson("/api/v1/reports/sales?from=2026-11-24&to=2026-11-24&group=day&branch_id={$other->id}")->assertJsonPath('data.totals.orders', 1);

    $this->actingAs(staff($shop, 'branch_manager'));
    $this->getJson('/api/v1/reports/sales?from=2026-11-24&to=2026-11-24&group=branch')->assertJsonCount(1, 'data.rows');
    $this->getJson("/api/v1/reports/sales?from=2026-11-24&to=2026-11-24&group=day&branch_id={$other->id}")->assertJsonPath('data.totals.orders', 0);
});

it('exports the report as an Excel file in the display unit', function () {
    $shop = shop();
    $this->actingAs(staff($shop, 'branch_manager'));
    salesWeek($shop);

    $response = $this->get('/api/v1/reports/sales/export?from=2026-11-23&to=2026-11-24&group=day')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('Content-Disposition', 'attachment; filename="sales-day-2026-11-23-2026-11-24.xlsx"');

    $sheet = sheetXml($response->getContent());
    expect($sheet)->toContain('فروش (تومان)')
        ->and($sheet)->toContain('۱۴۰۵/۰۹/۰۲')
        ->and($sheet)->toContain('<v>198000</v>')
        ->and($sheet)->toContain('جمع کل')
        ->and($sheet)->toContain('<v>550000</v>');

    // By item the totals row adds up the lines (before tax), like the rows.
    $byItem = sheetXml($this->get('/api/v1/reports/sales/export?from=2026-11-23&to=2026-11-24&group=item')->assertOk()->getContent());
    expect($byItem)->toContain('<v>500000</v>')->and($byItem)->not->toContain('<v>550000</v>');
});

it('validates the period and limits reports to those allowed', function () {
    $shop = shop();
    $this->actingAs(staff($shop, 'branch_manager'));

    $this->getJson('/api/v1/reports/sales?from=2026-11-24&to=2026-11-01&group=week')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to', 'group']);
    $this->getJson('/api/v1/reports/sales?from=2025-01-01&to=2026-11-01&group=day')->assertUnprocessable()->assertJsonValidationErrors('to');

    $this->actingAs(staff($shop));
    $this->getJson('/api/v1/reports/dashboard')->assertForbidden();
    $this->getJson('/api/v1/reports/sales?from=2026-11-24&to=2026-11-24&group=day')->assertForbidden();
});
