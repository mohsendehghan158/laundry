<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Notifications\Contracts\SmsGateway;
use App\Modules\Notifications\Gateways\KavenegarSmsGateway;
use App\Modules\Notifications\Gateways\LogSmsGateway;
use App\Modules\Notifications\Listeners\NotifyCustomer;
use App\Modules\Orders\Events\OrderStatusChanged;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, fn ($app): SmsGateway => match (config('services.sms.driver')) {
            'kavenegar' => new KavenegarSmsGateway(
                $app->make(Factory::class),
                (string) config('services.sms.kavenegar.key'),
                config('services.sms.kavenegar.sender') ?: null,
            ),
            'log', null => new LogSmsGateway,
            default => throw new InvalidArgumentException('Unknown SMS_DRIVER: '.config('services.sms.driver')),
        });
    }

    public function boot(): void
    {
        Event::listen(OrderStatusChanged::class, NotifyCustomer::class);
    }
}
