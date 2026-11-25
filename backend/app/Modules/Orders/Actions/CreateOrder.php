<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Billing\Models\PromoCode;
use App\Modules\Billing\Support\PromoCodes;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderPricer;
use App\Modules\Orders\Support\OrderWriter;
use App\Modules\Organization\Enums\BranchType;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Support\Sequences;
use App\Support\Exceptions\ApiException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class CreateOrder
{
    public function __construct(
        private readonly OrderPricer $pricer,
        private readonly OrderWriter $writer,
        private readonly Sequences $sequences,
        private readonly PromoCodes $promoCodes,
    ) {}

    /**
     * Takes an order in at the counter: prices it, gives it the branch's
     * next ticket number, tags every piece and records the first status.
     * With an idempotency key, a repeated request returns the order made
     * the first time; the second element tells whether it was just created.
     *
     * @param  array<string, mixed>  $data  validated StoreOrderRequest data
     * @return array{0: Order, 1: bool}
     */
    public function handle(User $actor, array $data, ?string $idempotencyKey = null): array
    {
        if ($idempotencyKey !== null && ($existing = Order::query()->where('idempotency_key', $idempotencyKey)->first())) {
            return [$existing, false];
        }

        $branch = Branch::query()->findOrFail($data['branch_id']);

        if (! $actor->canAccessBranch($branch) || ! $branch->is_active) {
            throw ApiException::conflict('order_branch_unavailable');
        }

        if ($branch->type === BranchType::Plant) {
            throw ApiException::conflict('order_branch_not_counter');
        }

        /** @var array{lines: list<array<string, mixed>>, turnaround_tier_id?: int|null, discount?: array{type: string, value: int|float|string}|null} $data */
        $priced = $this->pricer->price($data);
        $this->writer->authorizeDiscount($actor, $priced);

        try {
            $order = DB::transaction(function () use ($actor, $data, $branch, $priced, $idempotencyKey): Order {
                // Serialize orders using the same promo code, so usage limits hold.
                if ($priced['promo'] !== null) {
                    PromoCode::query()->lockForUpdate()->findOrFail($priced['promo']->id);
                    $this->promoCodes->apply($priced['promo']->code, $priced['subtotal'], (int) $data['customer_id']);
                }

                $receivedAt = now();
                $number = $this->sequences->next($branch->organization_id, "ticket:{$branch->id}");

                $order = Order::query()->create([
                    'branch_id' => $branch->id,
                    'customer_id' => $data['customer_id'],
                    'ticket_number' => $branch->code.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                    'status' => OrderStatus::Received,
                    'payment_status' => PaymentStatus::for($priced['total'], 0),
                    'received_at' => $receivedAt,
                    'due_at' => $this->pricer->dueAt($receivedAt, $priced['duration_hours']),
                    'notes' => $data['notes'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                    'created_by' => $actor->id,
                    'paid_amount' => 0,
                    'version' => 1,
                    ...$this->writer->attributes($priced, Arr::get($data, 'discount.reason')),
                ]);

                $this->writer->writeLines($order, $priced);

                $order->history()->create(['from_status' => null, 'to_status' => OrderStatus::Received, 'user_id' => $actor->id]);

                if ($priced['discount_amount'] > 0) {
                    activity('order')->causedBy($actor)->performedOn($order)->event('discounted')
                        ->withProperties(['amount' => $priced['discount_amount'], 'reason' => $order->discount_reason])
                        ->log('discounted');
                }

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Two identical retries raced; the other one won.
            if ($idempotencyKey !== null && ($existing = Order::query()->where('idempotency_key', $idempotencyKey)->first())) {
                return [$existing, false];
            }

            throw $e;
        }

        return [$order, true];
    }
}
