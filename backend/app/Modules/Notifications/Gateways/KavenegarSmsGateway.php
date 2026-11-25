<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Gateways;

use App\Modules\Notifications\Contracts\SmsGateway;
use Illuminate\Http\Client\Factory as Http;
use RuntimeException;
use Throwable;

/**
 * Kavenegar (kavenegar.com) simple send API. Needs KAVENEGAR_API_KEY and,
 * optionally, KAVENEGAR_SENDER (a dedicated line number).
 */
final class KavenegarSmsGateway implements SmsGateway
{
    public function __construct(
        private readonly Http $http,
        private readonly string $apiKey,
        private readonly ?string $sender,
    ) {}

    public function name(): string
    {
        return 'kavenegar';
    }

    public function send(string $to, string $text): string
    {
        try {
            $response = $this->http
                ->asForm()
                ->timeout(15)
                ->post("https://api.kavenegar.com/v1/{$this->apiKey}/sms/send.json", array_filter([
                    'receptor' => $to,
                    'message' => $text,
                    'sender' => $this->sender,
                ]));
        } catch (Throwable $e) {
            throw new RuntimeException('Kavenegar unreachable: '.$e->getMessage(), 0, $e);
        }

        $status = $response->json('return.status');

        if ($status !== 200) {
            throw new RuntimeException(sprintf('Kavenegar refused (%s): %s', $status ?? $response->status(), $response->json('return.message') ?? $response->body()));
        }

        return (string) $response->json('entries.0.messageid');
    }
}
