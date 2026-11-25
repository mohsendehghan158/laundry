<?php

declare(strict_types=1);

use App\Modules\Notifications\Models\NotificationLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-24 10:00', 'Asia/Tehran'));
    config(['app.url' => 'https://laundry.test', 'services.sms.driver' => 'log']);
});

function shirtLines(object $shop): array
{
    return ['lines' => [['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'quantity' => 1]]];
}

it('texts the customer when an order is taken in, with Jalali due date and tracking link', function () {
    $shop = shop();
    $this->actingAs(staff($shop));

    $order = $this->postJson('/api/v1/orders', orderPayload($shop, shirtLines($shop)))->assertCreated()->json('data');

    $log = NotificationLog::query()->sole();
    expect($log->event->value)->toBe('order_received')
        ->and($log->status)->toBe('sent')
        ->and($log->provider)->toBe('log')
        ->and($log->recipient)->toBe('09121112233')
        ->and($log->body)->toContain('مشتری نمونه عزیز')
        ->and($log->body)->toContain('T1-00001')
        // 72 working hours from Tuesday 10:00 → next Tuesday 10:00 = 1405/09/10.
        ->and($log->body)->toContain('موعد تحویل: ۱۴۰۵/۰۹/۱۰ ۱۰:۰۰')
        // 990,000 rials shown in toman.
        ->and($log->body)->toContain('مبلغ: ۹۹,۰۰۰ تومان')
        ->and($log->body)->toContain("https://laundry.test/track/{$order['public_id']}");
});

it('texts on ready and cancel, but not on completion unless switched on', function () {
    $shop = shop();
    $this->actingAs(staff($shop, 'branch_manager'));
    $first = $this->postJson('/api/v1/orders', orderPayload($shop, shirtLines($shop)))->json('data');
    $this->postJson("/api/v1/orders/{$first['id']}/transitions", ['to' => 'ready'])->assertOk();
    $this->postJson("/api/v1/orders/{$first['id']}/handover", ['piece_ids' => [$first['lines'][0]['pieces'][0]['id']]])->assertOk();

    $second = $this->postJson('/api/v1/orders', orderPayload($shop, shirtLines($shop)))->json('data');
    $this->postJson("/api/v1/orders/{$second['id']}/transitions", ['to' => 'cancelled', 'reason' => 'x'])->assertOk();

    expect(NotificationLog::query()->orderBy('id')->pluck('event')->map->value->all())
        ->toBe(['order_received', 'order_ready', 'order_received', 'order_cancelled']);
});

it('uses the organization\'s own wording and respects switched-off messages', function () {
    $shop = shop();
    $this->actingAs($this->owner($shop->organization));

    $this->getJson('/api/v1/notification-templates')
        ->assertOk()
        ->assertJsonPath('data.0.event', 'order_received')
        ->assertJsonPath('data.0.customized', false)
        ->assertJsonPath('data.2.is_active', false)
        ->assertJsonPath('placeholders.0', 'customer');

    $this->putJson('/api/v1/notification-templates/order_received', ['body' => 'قبض {ticket} — {branch}', 'is_active' => true])
        ->assertOk()
        ->assertJsonPath('data.customized', true);
    $this->putJson('/api/v1/notification-templates/order_ready', ['body' => 'x', 'is_active' => false])->assertOk();
    $this->putJson('/api/v1/notification-templates/no_such_event', ['body' => 'x', 'is_active' => true])->assertNotFound();

    $this->actingAs(staff($shop));
    $order = $this->postJson('/api/v1/orders', orderPayload($shop, shirtLines($shop)))->json('data');
    $this->postJson("/api/v1/orders/{$order['id']}/transitions", ['to' => 'ready'])->assertOk();

    expect(NotificationLog::query()->sole()->body)->toBe("قبض T1-00001 — {$shop->branch->name}");
});

it('sends through Kavenegar and records the provider id', function () {
    config(['services.sms.driver' => 'kavenegar', 'services.sms.kavenegar.key' => 'test-key', 'services.sms.kavenegar.sender' => '10004346']);
    Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 200, 'message' => 'تایید شد'], 'entries' => [['messageid' => 8792343]]])]);
    $shop = shop();
    $this->actingAs(staff($shop));

    $this->postJson('/api/v1/orders', orderPayload($shop, shirtLines($shop)))->assertCreated();

    expect(NotificationLog::query()->sole())->toMatchArray(['status' => 'sent', 'provider' => 'kavenegar', 'provider_message_id' => '8792343']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/test-key/sms/send.json')
        && $request['receptor'] === '09121112233'
        && $request['sender'] === '10004346');
});

it('keeps the order when the SMS provider fails, and marks the message failed for a retry', function () {
    config(['services.sms.driver' => 'kavenegar', 'services.sms.kavenegar.key' => 'key']);
    Http::fakeSequence('api.kavenegar.com/*')
        ->push(['return' => ['status' => 401, 'message' => 'API Key is invalid'], 'entries' => null])
        ->push(['return' => ['status' => 200], 'entries' => [['messageid' => 1]]]);
    $shop = shop();
    $this->actingAs(staff($shop, 'branch_manager'));

    $this->postJson('/api/v1/orders', orderPayload($shop, shirtLines($shop)))->assertCreated();

    $log = NotificationLog::query()->sole();
    expect($log->status)->toBe('failed')->and($log->error)->toContain('API Key is invalid');

    $this->getJson('/api/v1/notification-logs?filter[status]=failed')->assertJsonPath('meta.total', 1);
    $this->postJson("/api/v1/notification-logs/{$log->id}/retry")->assertForbidden();

    $this->actingAs($this->owner($shop->organization));
    $this->postJson("/api/v1/notification-logs/{$log->id}/retry")->assertOk()->assertJsonPath('data.status', 'sent');
    $this->postJson("/api/v1/notification-logs/{$log->id}/retry")->assertConflict()->assertJsonPath('code', 'notification_not_failed');
});

it('lets only the right roles see messages and templates', function () {
    $shop = shop();
    $this->actingAs(staff($shop));
    $this->getJson('/api/v1/notification-logs')->assertForbidden();
    $this->getJson('/api/v1/notification-templates')->assertForbidden();

    $this->actingAs(staff($shop, 'branch_manager'));
    $this->getJson('/api/v1/notification-logs')->assertOk();
    $this->getJson('/api/v1/notification-templates')->assertForbidden();
});

it('shows a public tracking page without personal details', function () {
    $shop = shop();
    $this->actingAs(staff($shop));
    $order = $this->postJson('/api/v1/orders', orderPayload($shop, shirtLines($shop)))->json('data');
    $this->postJson("/api/v1/orders/{$order['id']}/transitions", ['to' => 'ready'])->assertOk();

    $this->app['auth']->forgetGuards();

    $response = $this->getJson("/api/v1/public/track/{$order['public_id']}")
        ->assertOk()
        ->assertJsonPath('data.ticket_number', 'T1-00001')
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.organization', $shop->organization->name)
        ->assertJsonPath('data.piece_count', 1)
        ->assertJsonPath('data.balance', 990_000)
        ->assertJsonPath('data.currency_unit', 'toman')
        ->assertJsonPath('data.history.*.status', ['received', 'ready']);

    expect(json_encode($response->json(), JSON_UNESCAPED_UNICODE))->not->toContain('مشتری نمونه')->not->toContain('09121112233');

    $this->getJson('/api/v1/public/track/01JZZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();
    $this->getJson('/api/v1/public/track/not-an-id')->assertNotFound();
});
