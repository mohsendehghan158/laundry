<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Enums\NotificationEvent;
use App\Modules\Notifications\Jobs\SendSms;
use App\Modules\Notifications\Models\NotificationLog;
use App\Modules\Notifications\Support\Templates;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Organization\Tenancy\OrganizationContext;
use Throwable;

/**
 * Texts the customer when their order is received, ready, completed or
 * cancelled, if that message is switched on.
 */
final class NotifyCustomer
{
    public function __construct(
        private readonly Templates $templates,
        private readonly OrganizationContext $context,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        $type = NotificationEvent::forStatus($event->to);

        if ($type === null) {
            return;
        }

        $order = $event->order;

        $this->context->runFor($order->organization_id, function () use ($type, $order): void {
            $template = $this->templates->for($type);

            if (! $template['is_active']) {
                return;
            }

            $log = NotificationLog::query()->create([
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'event' => $type,
                'channel' => 'sms',
                'recipient' => $order->customer->mobile,
                'body' => $this->templates->render($template['body'], $order),
                'status' => 'queued',
            ]);

            try {
                SendSms::dispatch($log->id);
            } catch (Throwable $e) {
                // With a synchronous queue a provider failure would surface in
                // the request that changed the order; the order is saved and
                // the log row already says the message failed.
                report($e);
            }
        });
    }
}
