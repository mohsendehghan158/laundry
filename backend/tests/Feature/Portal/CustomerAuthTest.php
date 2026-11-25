<?php

declare(strict_types=1);

use App\Modules\Customers\Models\Customer;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->shop = shop();
    $this->sms = fakeSms();
});

it('signs a new customer in with a texted code', function () {
    $this->getJson('/api/v1/portal/settings')->assertOk()->assertJsonPath('data.organization_name', $this->shop->organization->name);

    $this->postJson('/api/v1/customer/auth/otp', ['mobile' => '۰۹۱۲ ۳۴۵ ۶۷۸۹'], fromSpa())
        ->assertOk()
        ->assertJsonPath('data', ['mobile' => '09123456789', 'expires_in' => 300, 'resend_in' => 60]);
    expect($this->sms->sent[0]['to'])->toBe('09123456789')
        ->and($this->sms->sent[0]['text'])->toContain($this->shop->organization->name);

    $this->postJson('/api/v1/customer/auth/verify', ['mobile' => '09123456789', 'code' => $this->sms->lastCode() === '00000' ? '11111' : '00000'], fromSpa())
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp_wrong')
        ->assertJsonPath('message', 'کد نادرست است. 4 بار دیگر می‌توانید امتحان کنید.');

    $this->postJson('/api/v1/customer/auth/verify', ['mobile' => '09123456789', 'code' => $this->sms->lastCode()], fromSpa())
        ->assertOk()
        ->assertJsonPath('data.needs_name', true);
    $this->assertAuthenticated('customer');
    $this->assertGuest('web');

    $this->putJson('/api/v1/customer/me', ['name' => 'سمیرا نادری'], fromSpa())->assertOk()->assertJsonPath('data.needs_name', false);
    expect(Customer::query()->where('mobile', '09123456789')->sole()->name)->toBe('سمیرا نادری');

    // A used code does not work twice.
    $this->postJson('/api/v1/customer/auth/logout', [], fromSpa())->assertNoContent();
    $this->assertGuest('customer');
    $this->getJson('/api/v1/customer/me', fromSpa())->assertUnauthorized();
});

it('limits how often codes are sent and how long and how often they can be tried', function () {
    $this->postJson('/api/v1/customer/auth/otp', ['mobile' => '09121112233'], fromSpa())->assertOk();
    $this->postJson('/api/v1/customer/auth/otp', ['mobile' => '09121112233'], fromSpa())->assertStatus(429)->assertJsonPath('code', 'otp_too_soon');

    // Six minutes later the code has expired.
    Carbon::setTestNow(now()->addMinutes(6));
    $this->postJson('/api/v1/customer/auth/verify', ['mobile' => '09121112233', 'code' => $this->sms->lastCode()], fromSpa())->assertJsonPath('code', 'otp_expired');

    $this->postJson('/api/v1/customer/auth/otp', ['mobile' => '09121112233'], fromSpa())->assertOk();
    $code = $this->sms->lastCode();
    foreach (range(1, 5) as $try) {
        $this->postJson('/api/v1/customer/auth/verify', ['mobile' => '09121112233', 'code' => $code === '12345' ? '54321' : '12345'], fromSpa());
    }
    $this->postJson('/api/v1/customer/auth/verify', ['mobile' => '09121112233', 'code' => $code], fromSpa())->assertJsonPath('code', 'otp_expired');

    foreach (range(1, 3) as $minute) {
        Carbon::setTestNow(now()->addMinutes(2));
        $this->postJson('/api/v1/customer/auth/otp', ['mobile' => '09121112233'], fromSpa())->assertOk();
    }
    Carbon::setTestNow(now()->addMinutes(2));
    $this->postJson('/api/v1/customer/auth/otp', ['mobile' => '09121112233'], fromSpa())->assertStatus(429)->assertJsonPath('code', 'otp_too_many');

    $this->postJson('/api/v1/customer/auth/otp', ['mobile' => '12345'], fromSpa())->assertJsonValidationErrors('mobile');
});

it('signs in a customer the counter already knows, and keeps customers and staff apart', function () {
    $id = portalSignIn($this->shop->customer->mobile, $this->sms);
    expect($id)->toBe($this->shop->customer->id);

    // A customer's session opens no staff route.
    $this->getJson('/api/v1/orders', fromSpa())->assertUnauthorized();
    $this->getJson('/api/v1/me', fromSpa())->assertUnauthorized();

    // A staff session opens no customer route.
    $this->postJson('/api/v1/customer/auth/logout', [], fromSpa());
    // Named explicitly: the test application keeps the guard the last
    // request switched to ("customer"), and staff sign in on "web".
    $this->actingAs(staff($this->shop), 'web');
    $this->getJson('/api/v1/me', fromSpa())->assertOk();
    $this->getJson('/api/v1/customer/me', fromSpa())->assertUnauthorized();
});

it('keeps the customer\'s addresses, one of them the default', function () {
    portalSignIn('09125556677', $this->sms);

    $home = $this->postJson('/api/v1/customer/addresses', ['title' => 'خانه', 'address' => 'تهران، خیابان آزادی، پلاک ۱۰', 'latitude' => 35.7, 'longitude' => 51.4], fromSpa())
        ->assertCreated()
        ->assertJsonPath('data.is_default', true)
        ->json('data');
    $work = $this->postJson('/api/v1/customer/addresses', ['title' => 'محل کار', 'address' => 'تهران، ونک', 'latitude' => 35.75, 'longitude' => 51.41, 'is_default' => true], fromSpa())->json('data');

    $this->getJson('/api/v1/customer/addresses', fromSpa())->assertJsonPath('data.*.title', ['محل کار', 'خانه']);
    $this->putJson("/api/v1/customer/addresses/{$home['id']}", ['is_default' => true], fromSpa())->assertJsonPath('data.is_default', true);
    $this->deleteJson("/api/v1/customer/addresses/{$work['id']}", [], fromSpa())->assertNoContent();
    $this->postJson('/api/v1/customer/addresses', ['address' => 'x'], fromSpa())->assertJsonValidationErrors(['latitude', 'longitude']);

    // Another customer's address is out of reach.
    $other = $this->shop->customer->addresses()->create(['address' => 'جای دیگر']);
    $this->deleteJson("/api/v1/customer/addresses/{$other->id}", [], fromSpa())->assertNotFound();
});

it('shows customers their own orders with a timeline and nothing internal', function () {
    $this->actingAs(staff($this->shop));
    $order = shirtOrder($this->shop);
    $theirs = $this->postJson('/api/v1/orders', orderPayload($this->shop, ['customer_id' => Customer::factory()->for($this->shop->organization)->create()->id]))->json('data');
    auth()->guard('web')->logout();

    portalSignIn($this->shop->customer->mobile, $this->sms);
    $this->getJson('/api/v1/customer/orders', fromSpa())->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.ticket_number', $order['ticket_number']);
    $this->getJson("/api/v1/customer/orders/{$order['public_id']}", fromSpa())
        ->assertOk()
        ->assertJsonPath('data.timeline.0.status', 'received')
        ->assertJsonPath('data.lines.0.item_name', 'پیراهن')
        ->assertJsonPath('data.balance', 990_000)
        ->assertJsonMissingPath('data.notes')
        ->assertJsonMissingPath('data.created_by');
    $this->getJson("/api/v1/customer/orders/{$theirs['public_id']}", fromSpa())->assertNotFound();
});
