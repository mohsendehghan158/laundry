<?php

declare(strict_types=1);

use App\Modules\Billing\Models\ExpenseCategory;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Tenancy\OrganizationContext;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-24 10:00', 'Asia/Tehran'));
});

function expenseCategory(object $shop, string $name = 'اجاره'): ExpenseCategory
{
    return app(OrganizationContext::class)->runFor($shop->organization->id, fn () => ExpenseCategory::query()->create(['name' => $name]));
}

function expensePayload(object $shop, ExpenseCategory $category, array $overrides = []): array
{
    return [
        'branch_id' => $shop->branch->id,
        'expense_category_id' => $category->id,
        'spent_on' => '2026-11-24',
        'amount' => 2_000_000,
        'method' => 'transfer',
        'description' => 'اجاره آذر',
        'reference' => '۴۴۵۵',
        ...$overrides,
    ];
}

it('starts an organization with default categories that can be managed', function () {
    $shop = shop();
    $this->actingAs($this->userWithRole('accountant', $shop->organization));

    $names = $this->getJson('/api/v1/expense-categories')->assertOk()->json('data.*.name');
    expect($names)->toContain('اجاره', 'حقوق و دستمزد', 'سایر');

    $created = $this->postJson('/api/v1/expense-categories', ['name' => 'تبلیغات'])->assertCreated()->json('data');
    $this->postJson('/api/v1/expense-categories', ['name' => 'تبلیغات'])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->putJson("/api/v1/expense-categories/{$created['id']}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
    $this->deleteJson("/api/v1/expense-categories/{$created['id']}")->assertNoContent();

    $this->getJson('/api/v1/expense-categories')->assertJsonCount(count($names), 'data');

    $this->actingAs(staff($shop, 'branch_manager'));
    $this->postJson('/api/v1/expense-categories', ['name' => 'دیگر'])->assertForbidden();
    $this->getJson('/api/v1/expense-categories')->assertOk();
});

it('records expenses and lists them with totals per category', function () {
    $shop = shop();
    $rent = expenseCategory($shop);
    $supplies = expenseCategory($shop, 'مواد شوینده');
    $this->actingAs(staff($shop, 'branch_manager'));

    $expense = $this->postJson('/api/v1/expenses', expensePayload($shop, $rent))->assertCreated()->json('data');
    expect($expense)->toMatchArray(['spent_on' => '2026-11-24', 'amount' => 2_000_000, 'method' => 'transfer', 'reference' => '4455', 'from_register' => false])
        ->and($expense['category'])->toBe(['id' => $rent->id, 'name' => 'اجاره']);
    $this->postJson('/api/v1/expenses', expensePayload($shop, $supplies, ['amount' => 300_000, 'description' => 'پودر', 'spent_on' => '2026-11-20']))->assertCreated();
    $this->postJson('/api/v1/expenses', expensePayload($shop, $supplies, ['amount' => 200_000, 'description' => 'نرم‌کننده']))->assertCreated();

    $this->getJson('/api/v1/expenses')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.description', 'نرم‌کننده')
        ->assertJsonPath('summary.total', 2_500_000)
        ->assertJsonPath('summary.by_category', [['id' => $rent->id, 'name' => 'اجاره', 'total' => 2_000_000], ['id' => $supplies->id, 'name' => 'مواد شوینده', 'total' => 500_000]]);
    $this->getJson('/api/v1/expenses?filter[from]=2026-11-21')->assertJsonCount(2, 'data')->assertJsonPath('summary.total', 2_200_000);
    $this->getJson("/api/v1/expenses?filter[category_id]={$supplies->id}&filter[q]=پودر")->assertJsonCount(1, 'data');

    $this->putJson("/api/v1/expenses/{$expense['id']}", ['amount' => 2_100_000, 'expense_category_id' => $supplies->id])->assertOk()->assertJsonPath('data.amount', 2_100_000);
    $this->deleteJson("/api/v1/expenses/{$expense['id']}")->assertNoContent();
    $this->getJson('/api/v1/expenses')->assertJsonPath('summary.total', 500_000);

    $this->postJson('/api/v1/expenses', [])->assertUnprocessable()->assertJsonValidationErrors(['branch_id', 'expense_category_id', 'spent_on', 'amount', 'method', 'description']);
});

it('takes cash expenses out of the open register and puts them back when deleted', function () {
    $shop = shop();
    $rent = expenseCategory($shop, 'سایر');
    $this->actingAs(staff($shop, 'branch_manager'));
    $cash = expensePayload($shop, $rent, ['method' => 'cash', 'amount' => 150_000, 'description' => 'نان', 'from_register' => true]);

    $this->postJson('/api/v1/expenses', $cash)->assertConflict()->assertJsonPath('code', 'shift_not_open');
    $this->postJson('/api/v1/expenses', [...$cash, 'method' => 'card'])->assertConflict()->assertJsonPath('code', 'expense_register_needs_cash');

    $shift = $this->postJson('/api/v1/cash-shifts', ['branch_id' => $shop->branch->id, 'opening_float' => 100_000])->assertCreated()->json('data');
    $this->postJson('/api/v1/expenses', $cash)->assertConflict()->assertJsonPath('code', 'shift_cash_insufficient');

    $expense = $this->postJson('/api/v1/expenses', [...$cash, 'amount' => 60_000])->assertCreated()->assertJsonPath('data.from_register', true)->json('data');
    $this->getJson("/api/v1/cash-shifts/{$shift['id']}")
        ->assertJsonPath('data.totals.cash_out', 60_000)
        ->assertJsonPath('data.totals.expected_cash', 40_000)
        ->assertJsonPath('data.movements.0.reason', 'هزینه: نان');

    $this->putJson("/api/v1/expenses/{$expense['id']}", ['amount' => 70_000])->assertConflict()->assertJsonPath('code', 'expense_from_register');
    $this->putJson("/api/v1/expenses/{$expense['id']}", ['description' => 'نان و پنیر', 'amount' => 60_000])->assertOk();

    $this->deleteJson("/api/v1/expenses/{$expense['id']}")->assertNoContent();
    $this->getJson("/api/v1/cash-shifts/{$shift['id']}")->assertJsonPath('data.totals.expected_cash', 100_000);

    $second = $this->postJson('/api/v1/expenses', [...$cash, 'amount' => 10_000])->assertCreated()->json('data');
    $this->postJson("/api/v1/cash-shifts/{$shift['id']}/close", ['counted_cash' => 90_000])->assertOk();
    $this->deleteJson("/api/v1/expenses/{$second['id']}")->assertConflict()->assertJsonPath('code', 'expense_shift_closed');
});

it('keeps expenses to their permissions and branches', function () {
    $shop = shop();
    $rent = expenseCategory($shop);
    $elsewhere = Branch::factory()->for($shop->organization)->create();

    $this->actingAs(staff($shop));
    $this->getJson('/api/v1/expenses')->assertForbidden();
    $this->postJson('/api/v1/expenses', expensePayload($shop, $rent))->assertForbidden();

    $this->actingAs(staff($shop, 'branch_manager'));
    $this->postJson('/api/v1/expenses', expensePayload($shop, $rent, ['branch_id' => $elsewhere->id]))->assertNotFound();
    $expense = $this->postJson('/api/v1/expenses', expensePayload($shop, $rent))->assertCreated()->json('data');
    $this->putJson("/api/v1/expenses/{$expense['id']}", ['branch_id' => $elsewhere->id])->assertNotFound();

    $this->actingAs($this->userWithRole('branch_manager', $shop->organization, [$elsewhere]));
    $this->getJson('/api/v1/expenses')->assertJsonCount(0, 'data')->assertJsonPath('summary.total', 0);
    $this->deleteJson("/api/v1/expenses/{$expense['id']}")->assertNotFound();

    $this->actingAs($this->userWithRole('accountant', $shop->organization));
    $this->getJson('/api/v1/expenses')->assertJsonCount(1, 'data');
});

it('shows expenses next to sales in the sales report', function () {
    $shop = shop();
    $rent = expenseCategory($shop);
    $supplies = expenseCategory($shop, 'مواد شوینده');
    $other = Branch::factory()->for($shop->organization)->create(['name' => 'شعبه دوم']);
    $this->actingAs($this->owner($shop->organization));

    shirtOrder($shop); // 990,000 with 90,000 VAT, on 2026-11-24.
    $this->postJson('/api/v1/expenses', expensePayload($shop, $rent, ['amount' => 500_000, 'spent_on' => '2026-11-23']))->assertCreated();
    $this->postJson('/api/v1/expenses', expensePayload($shop, $supplies, ['amount' => 100_000]))->assertCreated();
    $this->postJson('/api/v1/expenses', expensePayload($shop, $supplies, ['amount' => 50_000, 'branch_id' => $other->id]))->assertCreated();
    // Outside the period.
    $this->postJson('/api/v1/expenses', expensePayload($shop, $rent, ['amount' => 9_000_000, 'spent_on' => '2026-10-01']))->assertCreated();

    $this->getJson('/api/v1/reports/sales?from=2026-11-23&to=2026-11-24&group=day')
        ->assertOk()
        ->assertJsonPath('data.rows.*.label', ['2026-11-23', '2026-11-24'])
        ->assertJsonPath('data.rows.0.total', 0)
        ->assertJsonPath('data.rows.0.expenses', 500_000)
        ->assertJsonPath('data.rows.1.expenses', 150_000)
        ->assertJsonPath('data.totals.expenses', 650_000)
        ->assertJsonPath('data.totals.net', 900_000 - 650_000)
        ->assertJsonPath('data.expenses_by_category', [['id' => $rent->id, 'name' => 'اجاره', 'total' => 500_000], ['id' => $supplies->id, 'name' => 'مواد شوینده', 'total' => 150_000]]);

    $this->getJson('/api/v1/reports/sales?from=2026-11-23&to=2026-11-24&group=branch')
        ->assertJsonPath('data.rows.*.label', [$shop->branch->name, 'شعبه دوم'])
        ->assertJsonPath('data.rows.*.expenses', [600_000, 50_000])
        ->assertJsonPath('data.rows.1.total', 0);
    $this->getJson('/api/v1/reports/sales?from=2026-11-23&to=2026-11-24&group=item')->assertJsonPath('data.rows.0.expenses', null);
    $this->getJson("/api/v1/reports/sales?from=2026-11-23&to=2026-11-24&group=day&branch_id={$other->id}")->assertJsonPath('data.totals.expenses', 50_000);

    $sheet = sheetXml($this->get('/api/v1/reports/sales/export?from=2026-11-23&to=2026-11-24&group=day')->assertOk()->getContent());
    expect($sheet)->toContain('هزینه‌ها (تومان)')->and($sheet)->toContain('<v>65000</v>')->and($sheet)->toContain('<v>25000</v>');
});
