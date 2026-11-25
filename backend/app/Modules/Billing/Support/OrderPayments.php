<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Enums\PaymentKind;
use App\Modules\Billing\Enums\PaymentMethod;
use App\Modules\Billing\Enums\ShiftStatus;
use App\Modules\Billing\Models\CashShift;
use App\Modules\Billing\Models\Payment;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Support\Exceptions\ApiException;

/**
 * Writes a payment or refund and brings the order's paid amount and
 * payment status in line. Callers hold a lock on the order row.
 */
final class OrderPayments
{
    public function write(User $actor, Order $order, PaymentKind $kind, PaymentMethod $method, int $amount, ?string $reference, ?string $notes): Payment
    {
        $shift = CashShift::query()->where('branch_id', $order->branch_id)->where('status', ShiftStatus::Open)->first();

        // Cash goes through the drawer, so the register must be open.
        if ($method === PaymentMethod::Cash && $shift === null) {
            throw ApiException::conflict('shift_not_open');
        }

        $payment = Payment::query()->create([
            'organization_id' => $order->organization_id,
            'order_id' => $order->id,
            'branch_id' => $order->branch_id,
            'cash_shift_id' => $shift?->id,
            'kind' => $kind,
            'method' => $method,
            'amount' => $amount,
            'reference' => $reference,
            'notes' => $notes,
            'user_id' => $actor->id,
        ]);

        $paid = $order->paid_amount + $payment->signedAmount();

        $order->update([
            'paid_amount' => $paid,
            'payment_status' => $kind === PaymentKind::Refund && $paid === 0 ? PaymentStatus::Refunded : PaymentStatus::for($order->total, $paid),
            'version' => $order->version + 1,
        ]);

        return $payment;
    }
}
