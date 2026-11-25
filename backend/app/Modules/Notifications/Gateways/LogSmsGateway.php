<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Gateways;

use App\Modules\Notifications\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes messages to the application log instead of sending them. The
 * default for development and testing.
 */
final class LogSmsGateway implements SmsGateway
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $to, string $text): string
    {
        Log::info("SMS to {$to}: {$text}");

        return 'log-'.Str::lower(Str::random(12));
    }
}
