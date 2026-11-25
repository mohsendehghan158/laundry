<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\RecordPayment;
use App\Modules\Billing\Actions\RefundPayment;
use App\Modules\Billing\Enums\PaymentMethod;
use App\Modules\Billing\Http\Requests\PaymentRequest;
use App\Modules\Billing\Http\Requests\RefundRequest;
use App\Modules\Billing\Http\Resources\PaymentResource;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderPaymentController
{
    /**
     * Record a payment
     *
     * In advance, at handover or in parts with different methods. Fails
     * with 409 `payment_exceeds_balance` above what is still owed, and
     * `shift_not_open` for cash while the branch's register is closed.
     */
    public function store(PaymentRequest $request, Order $order, RecordPayment $recordPayment): JsonResponse
    {
        Gate::authorize('view', $order);

        $payment = $recordPayment->handle(
            $request->actor(),
            $order,
            PaymentMethod::from((string) $request->validated('method')),
            $request->integer('amount'),
            $request->validated('reference'),
            $request->validated('notes'),
        );

        return (new PaymentResource($payment->load('user')))->response()->setStatusCode(201);
    }

    /**
     * Refund a payment
     *
     * Up to what the customer has paid; the reason goes to the audit log.
     */
    public function refund(RefundRequest $request, Order $order, RefundPayment $refundPayment): JsonResponse
    {
        Gate::authorize('view', $order);

        $refund = $refundPayment->handle(
            $request->actor(),
            $order,
            PaymentMethod::from((string) $request->validated('method')),
            $request->integer('amount'),
            (string) $request->validated('reason'),
        );

        return (new PaymentResource($refund->load('user')))->response()->setStatusCode(201);
    }
}
