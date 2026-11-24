<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public static function for(int $total, int $paid): self
    {
        return match (true) {
            $paid <= 0 => $total === 0 ? self::Paid : self::Unpaid,
            $paid < $total => self::PartiallyPaid,
            default => self::Paid,
        };
    }
}
