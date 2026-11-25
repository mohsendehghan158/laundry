<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\PaymentKind;
use App\Modules\Billing\Enums\PaymentMethod;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Support\OrderPayments;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

final class RefundPayment
{
    public function __construct(private readonly OrderPayments $payments) {}

    /**
     * Gives money back to the customer, up to what they have paid. The
     * original payments stay; the refund is recorded next to them.
     */
    public function handle(User $actor, Order $order, PaymentMethod $method, int $amount, string $reason): Payment
    {
        return DB::transaction(function () use ($actor, $order, $method, $amount, $reason): Payment {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($amount > $order->paid_amount) {
                throw ApiException::conflict('refund_exceeds_paid');
            }

            $refund = $this->payments->write($actor, $order, PaymentKind::Refund, $method, $amount, null, $reason);

            activity('order')->causedBy($actor)->performedOn($order)->event('refunded')
                ->withProperties(['amount' => $amount, 'method' => $method->value, 'reason' => $reason])
                ->log('refunded');

            return $refund;
        });
    }
}
