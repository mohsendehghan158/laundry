<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

use App\Modules\Orders\Enums\OrderStatus;

/**
 * Moments the customer is told about, each with built-in wording that an
 * organization may replace. Placeholders: {customer} {ticket} {total}
 * {balance} {due} {link} {organization} {branch} {rack} {pickup}.
 */
enum NotificationEvent: string
{
    /** Placed online, waiting for pickup. */
    case OrderPlaced = 'order_placed';
    case OrderReceived = 'order_received';
    case OrderReady = 'order_ready';
    case OrderCompleted = 'order_completed';
    case OrderCancelled = 'order_cancelled';

    public const array PLACEHOLDERS = ['customer', 'ticket', 'total', 'balance', 'due', 'link', 'organization', 'branch', 'rack', 'pickup'];

    public static function forStatus(OrderStatus $status): ?self
    {
        return match ($status) {
            OrderStatus::PendingPickup => self::OrderPlaced,
            OrderStatus::Received => self::OrderReceived,
            OrderStatus::Ready => self::OrderReady,
            OrderStatus::Completed => self::OrderCompleted,
            OrderStatus::Cancelled => self::OrderCancelled,
            default => null,
        };
    }

    public function defaultBody(): string
    {
        return match ($this) {
            self::OrderPlaced => "{customer} عزیز، سفارش آنلاین {ticket} در {organization} ثبت شد.\nجمع‌آوری: {pickup}\nپیگیری: {link}",
            self::OrderReceived => "{customer} عزیز، سفارش {ticket} در {organization} ثبت شد.\nموعد تحویل: {due}\nمبلغ: {total} تومان\nپیگیری: {link}",
            self::OrderReady => "{customer} عزیز، سفارش {ticket} آماده تحویل است.\nمانده: {balance} تومان\n{organization}",
            self::OrderCompleted => 'سفارش {ticket} تحویل شد. از اعتماد شما به {organization} سپاسگزاریم.',
            self::OrderCancelled => 'سفارش {ticket} در {organization} لغو شد.',
        };
    }

    /** Whether the event is on until an organization says otherwise. */
    public function enabledByDefault(): bool
    {
        return $this !== self::OrderCompleted;
    }
}
