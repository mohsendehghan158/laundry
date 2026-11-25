<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\PaymentKind;
use App\Modules\Billing\Enums\PaymentMethod;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Support\OrderPayments;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

final class RecordPayment
{
    public function __construct(private readonly OrderPayments $payments) {}

    /**
     * Records money received for an order: in advance, at handover, or in
     * parts with different methods. Never more than what is still owed.
     */
    public function handle(User $actor, Order $order, PaymentMethod $method, int $amount, ?string $reference = null, ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($actor, $order, $method, $amount, $reference, $notes): Payment {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status === OrderStatus::Cancelled) {
                throw ApiException::conflict('payment_order_cancelled');
            }

            if ($amount > $order->balance()) {
                throw ApiException::conflict('payment_exceeds_balance');
            }

            return $this->payments->write($actor, $order, PaymentKind::Payment, $method, $amount, $reference, $notes);
        });
    }
}
