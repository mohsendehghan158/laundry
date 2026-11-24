<?php

declare(strict_types=1);

use App\Modules\Audit\Models\Activity;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Support\Settings;

it('returns defaults until a setting is changed', function () {
    $this->actingAsOwner();

    $this->getJson('/api/v1/settings')
        ->assertOk()
        ->assertExactJson(['data' => [
            'general' => ['currency_unit' => 'toman', 'timezone' => 'Asia/Tehran'],
            'tax' => ['vat_percent' => 10, 'prices_include_vat' => false],
            'receipt' => ['header_text' => null, 'footer_text' => null],
            'hours' => ['opening' => '09:00', 'closing' => '21:00', 'closed_days' => '5'],
            'orders' => ['max_discount_percent' => 10],
        ]]);
});

it('updates only the settings that were sent', function () {
    $this->actingAsOwner();

    $this->putJson('/api/v1/settings', [
        'tax' => ['vat_percent' => '9.5'],
        'receipt' => ['footer_text' => 'از اعتماد شما سپاسگزاریم'],
    ])
        ->assertOk()
        ->assertJsonPath('data.tax.vat_percent', 9.5)
        ->assertJsonPath('data.tax.prices_include_vat', false)
        ->assertJsonPath('data.receipt.footer_text', 'از اعتماد شما سپاسگزاریم')
        ->assertJsonPath('data.general.currency_unit', 'toman');

    $this->assertDatabaseCount('settings', 2);
});

it('logs what changed and skips writes that change nothing', function () {
    $owner = $this->actingAsOwner();

    $this->putJson('/api/v1/settings', ['general' => ['currency_unit' => 'rial'], 'tax' => ['vat_percent' => 10]])
        ->assertOk();

    $entry = Activity::query()->where('log_name', 'settings')->sole();
    expect($entry->causer_id)->toBe($owner->id)
        ->and($entry->attribute_changes->all())->toBe([
            'attributes' => ['general.currency_unit' => 'rial'],
            'old' => ['general.currency_unit' => 'toman'],
        ]);

    $this->putJson('/api/v1/settings', ['general' => ['currency_unit' => 'rial']])->assertOk();
    expect(Activity::query()->where('log_name', 'settings')->count())->toBe(1);
});

it('validates setting values', function () {
    $this->actingAsOwner();

    $response = $this->putJson('/api/v1/settings', [
        'general' => ['currency_unit' => 'dollar', 'timezone' => 'Mars/Olympus'],
        'tax' => ['vat_percent' => 150, 'prices_include_vat' => 'maybe'],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'general.currency_unit', 'general.timezone', 'tax.vat_percent', 'tax.prices_include_vat',
        ]);

    expect($response->json('errors')['tax.vat_percent'][0])
        ->toBe('درصد مالیات بر ارزش افزوده نباید بزرگ‌تر از 100 باشد.');

    $this->assertDatabaseCount('settings', 0);
});

it('keeps settings separate per organization', function () {
    $this->actingAsOwner();
    $this->putJson('/api/v1/settings', ['general' => ['currency_unit' => 'rial']])->assertOk();

    $this->actingAsOwner();
    $this->getJson('/api/v1/settings')->assertJsonPath('data.general.currency_unit', 'toman');
});

it('reads a single setting for use inside the application', function () {
    $this->actingAsOwner();
    $this->putJson('/api/v1/settings', ['tax' => ['prices_include_vat' => true]])->assertOk();

    expect(app(Settings::class)->get('tax.prices_include_vat'))->toBeTrue()
        ->and(app(Settings::class)->get('tax.vat_percent'))->toBe(10);
});

it('requires permission to view or change settings', function () {
    $this->actingAs($this->userWithRole('cashier'));
    $this->getJson('/api/v1/settings')->assertForbidden();

    $this->actingAs($this->userWithRole('branch_manager'));
    $this->getJson('/api/v1/settings')->assertOk();
    $this->putJson('/api/v1/settings', ['tax' => ['vat_percent' => 0]])->assertForbidden();
});

it('shows the organization to every member but lets only permitted users rename it', function () {
    $organization = Organization::factory()->create(['name' => 'پاکان']);

    $this->actingAs($this->userWithRole('cashier', $organization));
    $this->getJson('/api/v1/organization')->assertOk()->assertJsonPath('data.name', 'پاکان');
    $this->putJson('/api/v1/organization', ['name' => 'x'])->assertForbidden();

    $this->actingAsOwner($organization);
    $this->putJson('/api/v1/organization', ['name' => ''])->assertUnprocessable();
    $this->putJson('/api/v1/organization', ['name' => 'خشکشویی پاکان'])
        ->assertOk()
        ->assertJsonPath('data.name', 'خشکشویی پاکان');

    expect(Activity::query()->where('log_name', 'organization')->where('event', 'updated')->count())->toBe(1);
});

it('validates working hours and closed days', function () {
    $this->actingAsOwner();

    $this->putJson('/api/v1/settings', ['hours' => ['opening' => '8:00', 'closed_days' => '5,7']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['hours.opening', 'hours.closed_days']);

    $this->putJson('/api/v1/settings', ['hours' => ['opening' => '08:30', 'closed_days' => '4,5']])
        ->assertOk()
        ->assertJsonPath('data.hours.opening', '08:30')
        ->assertJsonPath('data.hours.closed_days', '4,5');
});
