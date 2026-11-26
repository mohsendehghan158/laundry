<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderPricer;
use App\Modules\Orders\Support\OrderWriter;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateOrder
{
    public function __construct(
        private readonly OrderPricer $pricer,
        private readonly OrderWriter $writer,
    ) {}

    /**
     * Replaces the lines of an order that has not been worked on yet.
     * Pieces are tagged again (the counter reprints the tags), the ticket
     * number and due date stay, and the old and new totals go to the audit
     * log with the reason.
     *
     * @param  array<string, mixed>  $data  validated UpdateOrderRequest data
     */
    public function handle(User $actor, Order $order, array $data): Order
    {
        if (! $order->status->isEditable()) {
            throw ApiException::conflict('order_not_editable');
        }

        if ((int) $data['version'] !== $order->version) {
            throw ApiException::conflict('order_version_conflict');
        }

        /** @var array{lines: list<array<string, mixed>>, turnaround_tier_id?: int|null, discount?: array{type: string, value: int|float|string}|null} $pricing */
        $pricing = $data;
        $priced = $this->pricer->price([...$pricing, 'customer_id' => $order->customer_id], $order->id);
        $this->writer->authorizeDiscount($actor, $priced);

        if ($priced['total'] < $order->paid_amount) {
            throw ApiException::conflict('order_total_below_paid');
        }

        return DB::transaction(function () use ($actor, $order, $data, $priced): Order {
            $before = ['total' => $order->total, 'lines' => $order->lines()->count()];

            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->version !== $order->version) {
                throw ApiException::conflict('order_version_conflict');
            }

            $order->lines()->delete();
            $order->fill([
                ...$this->writer->attributes($priced, Arr::get($data, 'discount.reason')),
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $order->notes,
                'payment_status' => PaymentStatus::for($priced['total'], $order->paid_amount),
                'version' => $order->version + 1,
            ])->save();

            $this->writer->writeLines($order, $priced);

            activity('order')->causedBy($actor)->performedOn($order)->event('edited')
                ->withChanges([
                    'attributes' => ['total' => $order->total, 'lines' => count($priced['lines'])],
                    'old' => $before,
                ])
                ->withProperties(['reason' => $data['reason']])
                ->log('edited');

            return $order;
        });
    }
}
