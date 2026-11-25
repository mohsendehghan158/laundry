<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\NotificationEvent;
use App\Modules\Notifications\Models\NotificationTemplate;
use App\Modules\Orders\Models\Order;
use App\Modules\Organization\Support\Settings;
use App\Support\Text\Digits;
use App\Support\Text\Jalali;
use Carbon\CarbonInterface;

/**
 * The wording of each message (an organization's own or the built-in
 * one) and filling it in for an order.
 */
final class Templates
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array{body: string, is_active: bool, customized: bool}
     */
    public function for(NotificationEvent $event): array
    {
        $template = NotificationTemplate::query()->where('event', $event)->first();

        return $template === null
            ? ['body' => $event->defaultBody(), 'is_active' => $event->enabledByDefault(), 'customized' => false]
            : ['body' => $template->body, 'is_active' => $template->is_active, 'customized' => true];
    }

    /**
     * Replaces {placeholders} with the order's details. Amounts are shown
     * in the organization's display unit with Persian digits, dates on
     * the Jalali calendar; the tracking link stays in Latin characters.
     */
    public function render(string $body, Order $order): string
    {
        $timezone = (string) $this->settings->get('general.timezone');
        $toman = $this->settings->get('general.currency_unit') === 'toman';
        $money = fn (int $rial): string => Digits::toPersian(number_format($toman ? intdiv($rial, 10) : $rial));

        $values = [
            'customer' => $order->customer->name,
            'ticket' => $order->ticket_number,
            'total' => $money($order->total),
            'balance' => $money($order->balance()),
            'due' => $order->due_at instanceof CarbonInterface ? Jalali::format($order->due_at, $timezone) : '',
            'link' => rtrim((string) config('app.url'), '/').'/track/'.$order->public_id,
            'organization' => $order->organization()->firstOrFail()->name,
            'branch' => $order->branch->name,
            'rack' => (string) $order->rack_location,
        ];

        return strtr($body, array_combine(array_map(fn (string $key): string => '{'.$key.'}', array_keys($values)), $values));
    }
}
