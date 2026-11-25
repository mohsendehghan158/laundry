<?php

declare(strict_types=1);

use App\Modules\Audit\Models\Activity;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use Illuminate\Support\Facades\Hash;

it('signs a user in and returns their profile, permissions and branches', function () {
    $user = $this->userWithRole('branch_manager');
    $branch = Branch::factory()->for($user->organization)->create(['name' => 'شعبه یک']);
    Branch::factory()->for($user->organization)->create(['name' => 'شعبه دو']);
    $user->branches()->attach($branch);

    $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'password'], fromSpa())
        ->assertOk()
        ->assertJsonPath('data.username', $user->username)
        ->assertJsonPath('data.organization.id', $user->organization_id)
        ->assertJsonPath('data.organization.currency_unit', 'toman')
        ->assertJsonPath('data.roles', ['branch_manager'])
        ->assertJsonPath('data.branches.*.name', ['شعبه یک'])
        ->assertJsonMissingPath('data.password');

    $this->assertAuthenticatedAs($user, 'web');
    expect($user->refresh()->last_login_at)->not->toBeNull();
    expect(Activity::query()->where('event', 'login')->where('causer_id', $user->id)->exists())->toBeTrue();
});

it('accepts a username typed in upper case or with Persian digits', function () {
    $user = User::factory()->create(['username' => 'cashier12']);

    $this->postJson('/api/v1/auth/login', ['username' => ' Cashier۱۲ ', 'password' => 'password'], fromSpa())
        ->assertOk();

    $this->assertAuthenticatedAs($user, 'web');
});

it('rejects a wrong password with a field error', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'nope'], fromSpa())
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('errors.username.0', 'نام کاربری یا رمز عبور نادرست است.');

    $this->assertGuest('web');
});

it('gives the same answer for an unknown username', function () {
    $this->postJson('/api/v1/auth/login', ['username' => 'nobody', 'password' => 'password'], fromSpa())
        ->assertUnprocessable()
        ->assertJsonPath('errors.username.0', 'نام کاربری یا رمز عبور نادرست است.');
});

it('refuses inactive and deleted accounts', function () {
    $inactive = User::factory()->inactive()->create();
    $deleted = User::factory()->create();
    $deleted->delete();

    $this->postJson('/api/v1/auth/login', ['username' => $inactive->username, 'password' => 'password'], fromSpa())
        ->assertUnprocessable()
        ->assertJsonPath('errors.username.0', 'این حساب کاربری غیرفعال است.');

    $this->postJson('/api/v1/auth/login', ['username' => $deleted->username, 'password' => 'password'], fromSpa())
        ->assertUnprocessable();

    $this->assertGuest('web');
});

it('locks a username out after five failed attempts', function () {
    $user = User::factory()->create();
    $attempt = fn (string $password) => $this->postJson(
        '/api/v1/auth/login',
        ['username' => $user->username, 'password' => $password],
        fromSpa(),
    );

    foreach (range(1, 5) as $ignored) {
        $attempt('wrong')->assertUnprocessable();
    }

    // Even the right password is refused while the lockout lasts.
    $attempt('password')
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_requests')
        ->assertJsonStructure(['errors' => ['username']]);
});

it('requires a session for protected endpoints', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'برای ادامه باید وارد شوید.', 'code' => 'unauthenticated']);
});

it('keeps the session across requests and ends it on sign out', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'password'], fromSpa())
        ->assertOk();

    $this->getJson('/api/v1/me', fromSpa())->assertOk()->assertJsonPath('data.id', $user->id);

    $this->postJson('/api/v1/auth/logout', [], fromSpa())->assertNoContent();

    $this->assertGuest('web');
    expect(Activity::query()->where('event', 'logout')->where('causer_id', $user->id)->exists())->toBeTrue();
});

it('lets a user change their own password', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->putJson('/api/v1/me/password', [
        'current_password' => 'wrong',
        'password' => 'new-secret-1',
        'password_confirmation' => 'new-secret-1',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

    $this->putJson('/api/v1/me/password', [
        'current_password' => 'password',
        'password' => 'new-secret-1',
        'password_confirmation' => 'different',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');

    $this->putJson('/api/v1/me/password', [
        'current_password' => 'password',
        'password' => 'new-secret-1',
        'password_confirmation' => 'new-secret-1',
    ])->assertNoContent();

    expect(Hash::check('new-secret-1', $user->refresh()->password))->toBeTrue();
});
