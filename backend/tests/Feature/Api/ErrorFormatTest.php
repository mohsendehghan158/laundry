<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('answers unknown API addresses with a JSON error', function () {
    $this->get('/api/v1/nowhere')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['message' => 'مورد درخواستی پیدا نشد.', 'code' => 'not_found']);
});

it('answers a missing record the same way', function () {
    $this->actingAsOwner();

    $this->getJson('/api/v1/branches/999999')
        ->assertNotFound()
        ->assertExactJson(['message' => 'مورد درخواستی پیدا نشد.', 'code' => 'not_found']);
});

it('reports a wrong HTTP method', function () {
    $this->actingAsOwner();

    $this->deleteJson('/api/v1/me')->assertMethodNotAllowed()->assertJsonPath('code', 'method_not_allowed');
});

it('answers in English when the client asks for it', function () {
    $this->getJson('/api/v1/me', ['Accept-Language' => 'en'])
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'You need to sign in to continue.', 'code' => 'unauthenticated']);

    $this->actingAsOwner();
    $this->postJson('/api/v1/branches', [], ['Accept-Language' => 'en-GB,en;q=0.9'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The given data was invalid.')
        ->assertJsonPath('errors.name.0', 'The name field is required.');
});

it('falls back to Persian for languages it does not speak', function () {
    $this->getJson('/api/v1/me', ['Accept-Language' => 'de'])
        ->assertJsonPath('message', 'برای ادامه باید وارد شوید.');
});

it('hides the details of unexpected failures', function () {
    config(['app.debug' => false]);
    Route::middleware('api')->get('/api/v1/boom', fn () => throw new RuntimeException('database password is hunter2'));

    $this->getJson('/api/v1/boom')
        ->assertInternalServerError()
        ->assertExactJson(['message' => 'خطایی در سامانه رخ داد. دوباره تلاش کنید.', 'code' => 'server_error']);
});

it('caps the page size', function () {
    $this->actingAsOwner();

    $this->getJson('/api/v1/users?per_page=100000')->assertOk()->assertJsonPath('meta.per_page', 100);
    $this->getJson('/api/v1/users?per_page=0')->assertOk()->assertJsonPath('meta.per_page', 1);
    $this->getJson('/api/v1/users')->assertOk()->assertJsonPath('meta.per_page', 25);
});

it('reports application health without authentication', function () {
    $this->get('/up')->assertOk();
});
