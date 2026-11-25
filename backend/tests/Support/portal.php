<?php

declare(strict_types=1);

use App\Modules\Notifications\Contracts\SmsGateway;

/**
 * Sanctum only starts a cookie session for requests coming from the SPA,
 * which it recognises by the Origin header.
 */
function fromSpa(): array
{
    return ['Origin' => 'http://localhost:3000'];
}

/** A text-message gateway that keeps what it was asked to send. */
function fakeSms(): object
{
    $gateway = new class implements SmsGateway
    {
        /** @var list<array{to: string, text: string}> */
        public array $sent = [];

        public function name(): string
        {
            return 'fake';
        }

        public function send(string $to, string $text): string
        {
            $this->sent[] = ['to' => $to, 'text' => $text];

            return (string) count($this->sent);
        }

        /** The five-digit code in the last message. */
        public function lastCode(): string
        {
            preg_match('/\d{5}/', end($this->sent)['text'] ?? '', $match);

            return $match[0] ?? '';
        }
    };
    app()->instance(SmsGateway::class, $gateway);

    return $gateway;
}

/** Signs a customer in to the portal the way a phone would, and returns their record id. */
function portalSignIn(string $mobile, object $sms): int
{
    test()->postJson('/api/v1/customer/auth/otp', ['mobile' => $mobile], fromSpa())->assertOk();

    return test()->postJson('/api/v1/customer/auth/verify', ['mobile' => $mobile, 'code' => $sms->lastCode()], fromSpa())->assertOk()->json('data.id');
}
