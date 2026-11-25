<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Contracts\SmsGateway;
use App\Modules\Notifications\Models\NotificationLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends one logged message through the configured gateway, retrying a
 * few times when the provider fails. The log row records the outcome.
 */
final class SendSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> seconds before each retry */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $logId) {}

    public function handle(SmsGateway $gateway): void
    {
        // Queued jobs have no signed-in user, so no organization scope applies.
        $log = NotificationLog::query()->withoutGlobalScopes()->find($this->logId);

        if ($log === null || $log->status === 'sent') {
            return;
        }

        $log->increment('attempts');

        $messageId = $gateway->send($log->recipient, $log->body);

        $log->update([
            'status' => 'sent',
            'provider' => $gateway->name(),
            'provider_message_id' => $messageId,
            'error' => null,
            'sent_at' => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        NotificationLog::query()->withoutGlobalScopes()->whereKey($this->logId)->update([
            'status' => 'failed',
            'error' => mb_substr($e->getMessage(), 0, 500),
        ]);
    }
}
