<?php

declare(strict_types=1);

use App\Modules\Customers\Models\Customer;
use App\Modules\Organization\Models\Organization;

it('finds customers by part of the name or mobile, typed with any digits', function () {
    $owner = $this->actingAsOwner();
    Customer::factory()->for($owner->organization)->create(['name' => 'محمد حسینی', 'mobile' => '09121112233']);
    Customer::factory()->for($owner->organization)->create(['name' => 'فاطمه رحیمی', 'mobile' => '09352223344']);
    Customer::factory()->for(Organization::factory())->create(['name' => 'محمد بیگانه', 'mobile' => '09121119999']);

    $this->getJson('/api/v1/customers?filter[q]=محمد')->assertJsonPath('data.*.name', ['محمد حسینی']);
    $this->getJson('/api/v1/customers?filter[q]=۰۹۳۵')->assertJsonPath('data.*.name', ['فاطمه رحیمی']);
    $this->getJson('/api/v1/customers?filter[q]=1122')->assertJsonPath('data.*.name', ['محمد حسینی']);
    $this->getJson('/api/v1/customers?filter[q]=+98935')->assertJsonPath('data.*.name', ['فاطمه رحیمی']);
    $this->getJson('/api/v1/customers?sort=name')->assertJsonPath('meta.total', 2);
});

it('creates a customer with a normalized mobile and addresses', function () {
    $owner = $this->actingAsOwner();

    $response = $this->postJson('/api/v1/customers', [
        'name' => 'زهرا محمدی',
        'mobile' => '+98 912 123 4567',
        'alert' => 'لباس‌ها را با آویز تحویل دهید.',
        'addresses' => [
            ['title' => 'خانه', 'address' => 'تهران، ونک'],
            ['title' => 'محل کار', 'address' => 'تهران، میرداماد', 'is_default' => true],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.mobile', '09121234567')
        ->assertJsonPath('data.alert', 'لباس‌ها را با آویز تحویل دهید.')
        ->assertJsonPath('data.addresses.0.title', 'محل کار')
        ->assertJsonPath('data.addresses.0.is_default', true)
        ->assertJsonPath('data.addresses.1.is_default', false);

    expect(Customer::query()->findOrFail($response->json('data.id'))->created_by)->toBe($owner->id);
});

it('keeps mobile numbers unique within an organization only', function () {
    $owner = $this->actingAsOwner();
    Customer::factory()->for($owner->organization)->create(['mobile' => '09121234567']);
    Customer::factory()->for(Organization::factory())->create(['mobile' => '09129999999']);

    $this->postJson('/api/v1/customers', ['name' => 'x', 'mobile' => '۰۹۱۲۱۲۳۴۵۶۷'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('mobile');
    $this->postJson('/api/v1/customers', ['name' => 'x', 'mobile' => '09129999999'])->assertCreated();
    $this->postJson('/api/v1/customers', ['name' => 'x', 'mobile' => '021-88001122'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('mobile');
});

it('replaces the address list on update, keeping listed ids', function () {
    $owner = $this->actingAsOwner();
    $customer = Customer::factory()->for($owner->organization)->create();
    $home = $customer->addresses()->create(['title' => 'خانه', 'address' => 'الف', 'is_default' => true]);
    $customer->addresses()->create(['title' => 'قدیمی', 'address' => 'ب']);

    $this->putJson("/api/v1/customers/{$customer->id}", [
        'addresses' => [
            ['id' => $home->id, 'title' => 'خانه', 'address' => 'الف جدید'],
            ['title' => 'باشگاه', 'address' => 'ج'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.addresses.*.address', ['الف جدید', 'ج'])
        ->assertJsonPath('data.addresses.0.id', $home->id);

    // Updating other fields leaves addresses alone.
    $this->putJson("/api/v1/customers/{$customer->id}", ['name' => 'نام تازه'])
        ->assertOk()
        ->assertJsonCount(2, 'data.addresses');
});

it('does not accept another customer\'s address id', function () {
    $owner = $this->actingAsOwner();
    $customer = Customer::factory()->for($owner->organization)->create();
    $other = Customer::factory()->for($owner->organization)->create();
    $foreign = $other->addresses()->create(['address' => 'x']);

    $this->putJson("/api/v1/customers/{$customer->id}", ['addresses' => [['id' => $foreign->id, 'address' => 'y']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('addresses.0.id');
});

it('applies customer permissions per role', function () {
    $organization = Organization::factory()->create();
    $customer = Customer::factory()->for($organization)->create();

    $this->actingAs($this->userWithRole('cashier', $organization));
    $this->getJson('/api/v1/customers')->assertOk();
    $this->postJson('/api/v1/customers', ['name' => 'x', 'mobile' => '09121234567'])->assertCreated();
    $this->deleteJson("/api/v1/customers/{$customer->id}")->assertForbidden();

    $this->actingAs($this->userWithRole('driver', $organization));
    $this->getJson("/api/v1/customers/{$customer->id}")->assertOk();
    $this->putJson("/api/v1/customers/{$customer->id}", ['name' => 'x'])->assertForbidden();

    $this->actingAs($this->userWithRole('plant_operator', $organization));
    $this->getJson('/api/v1/customers')->assertForbidden();
});

it('hides customers of other organizations', function () {
    $this->actingAsOwner();
    $foreign = Customer::factory()->for(Organization::factory())->create();

    $this->getJson("/api/v1/customers/{$foreign->id}")->assertNotFound();
    $this->deleteJson("/api/v1/customers/{$foreign->id}")->assertNotFound();
});

it('soft deletes a customer and frees their mobile number', function () {
    $owner = $this->actingAsOwner();
    $customer = Customer::factory()->for($owner->organization)->create(['mobile' => '09121234567']);

    $this->deleteJson("/api/v1/customers/{$customer->id}")->assertNoContent();
    $this->assertSoftDeleted($customer);
    $this->postJson('/api/v1/customers', ['name' => 'x', 'mobile' => '09121234567'])->assertCreated();
});
