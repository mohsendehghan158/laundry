<?php

declare(strict_types=1);

namespace App\Modules\B2B\Actions;

use App\Modules\B2B\Enums\DeliveryNoteStatus;
use App\Modules\B2B\Enums\DeliveryNoteType;
use App\Modules\B2B\Models\DeliveryNote;
use App\Modules\B2B\Models\DeliveryNoteLine;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Actions\HandOverOrder;
use App\Modules\Orders\Enums\PieceStatus;
use App\Modules\Orders\Models\Order;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

final class ConfirmDeliveryNote
{
    public function __construct(
        private readonly CreateOrder $createOrder,
        private readonly HandOverOrder $handOverOrder,
    ) {}

    /**
     * Closes a delivery note with the name of whoever signed for the
     * company. An incoming note becomes an order of the counted items at
     * the company's prices; an outgoing one records the company's count
     * and hands over the order's ready pieces. Differences stay on the note.
     *
     * @param  array<int, int|float|string>  $received  outgoing notes: the company's count per line id
     */
    public function handle(User $actor, DeliveryNote $note, string $counterpart, array $received = []): DeliveryNote
    {
        return DB::transaction(function () use ($actor, $note, $counterpart, $received): DeliveryNote {
            $note = DeliveryNote::query()->lockForUpdate()->findOrFail($note->id);

            if ($note->status !== DeliveryNoteStatus::Draft) {
                throw ApiException::conflict('delivery_note_confirmed');
            }

            if ($note->type === DeliveryNoteType::In) {
                $note->order_id = $this->intake($actor, $note)->id;
            } else {
                foreach ($note->lines()->get() as $line) {
                    if (array_key_exists($line->id, $received)) {
                        $line->update(['counted_quantity' => $received[$line->id]]);
                    }
                }
                $order = Order::query()->findOrFail($note->order_id);
                $ready = $order->pieces()->where('status', PieceStatus::Ready)->pluck('id')->all();
                $this->handOverOrder->handle($actor, $order, array_values(array_map(intval(...), $ready)));
            }

            $note->fill([
                'status' => DeliveryNoteStatus::Confirmed,
                'counterpart_name' => $counterpart,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
            ])->save();

            activity('company')->causedBy($actor)->performedOn($note)->event('delivery_confirmed')
                ->withProperties(['number' => $note->number, 'type' => $note->type->value, 'order_id' => $note->order_id])
                ->log('delivery_confirmed');

            return $note;
        });
    }

    private function intake(User $actor, DeliveryNote $note): Order
    {
        $lines = $note->lines()->get()
            ->filter(fn (DeliveryNoteLine $line): bool => (float) $line->counted_quantity > 0 && $line->item_id !== null && $line->service_type_id !== null)
            ->map(fn (DeliveryNoteLine $line): array => [
                'item_id' => $line->item_id,
                'service_type_id' => $line->service_type_id,
                'quantity' => (string) (float) $line->counted_quantity,
            ])
            ->values()
            ->all();

        if ($lines === []) {
            throw ApiException::conflict('delivery_note_empty');
        }

        [$order] = $this->createOrder->handle($actor, [
            'branch_id' => $note->branch_id,
            'customer_id' => $note->customer_id,
            'company_department_id' => $note->company_department_id,
            'lines' => $lines,
            'notes' => __('b2b.order_from_note', ['number' => $note->number]),
        ]);

        return $order;
    }
}
