<?php

declare(strict_types=1);

namespace App\Modules\B2B\Actions;

use App\Modules\B2B\Enums\DeliveryNoteStatus;
use App\Modules\B2B\Enums\DeliveryNoteType;
use App\Modules\B2B\Models\Company;
use App\Modules\B2B\Models\DeliveryNote;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ServiceType;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Support\Sequences;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveDeliveryNote
{
    public function __construct(private readonly Sequences $sequences) {}

    /**
     * Records a delivery note as a draft, or changes a draft.
     *
     * In: the company's customer, the branch and per line an item and
     * service with what the company stated and what was counted here.
     * Out: an order of the company and per order line what was sent; the
     * company's own count is filled in when it signs.
     *
     * @param  array<string, mixed>  $data  validated SaveDeliveryNoteRequest data
     */
    public function handle(User $actor, array $data, ?DeliveryNote $note = null): DeliveryNote
    {
        if ($note !== null && $note->status !== DeliveryNoteStatus::Draft) {
            throw ApiException::conflict('delivery_note_confirmed');
        }

        $type = $note === null ? DeliveryNoteType::from((string) $data['type']) : $note->type;
        if ($note !== null) {
            // Fields left out of a change keep their values.
            $data += [
                'branch_id' => $note->branch_id,
                'customer_id' => $note->customer_id,
                'company_department_id' => $note->company_department_id,
                'order_id' => $note->order_id,
                'lines' => $note->lines()->get()->map(fn ($line): array => [
                    'item_id' => $line->item_id,
                    'service_type_id' => $line->service_type_id,
                    'order_line_id' => $line->order_line_id,
                    'expected_quantity' => $line->expected_quantity,
                    'counted_quantity' => $line->counted_quantity,
                ])->all(),
            ];
        }
        $company = $note === null ? Company::query()->where('is_active', true)->findOrFail((int) $data['company_id']) : $note->company;

        [$attributes, $lines] = $type === DeliveryNoteType::In
            ? $this->incoming($actor, $company, $data)
            : $this->outgoing($actor, $company, $data);

        return DB::transaction(function () use ($actor, $note, $type, $company, $attributes, $lines, $data): DeliveryNote {
            if ($note === null) {
                $note = DeliveryNote::query()->create([
                    ...$attributes,
                    'type' => $type,
                    'company_id' => $company->id,
                    'number' => $this->sequences->next($company->organization_id, 'delivery_note'),
                    'status' => DeliveryNoteStatus::Draft,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $actor->id,
                ]);
            } else {
                $note->update([...$attributes, 'notes' => array_key_exists('notes', $data) ? $data['notes'] : $note->notes]);
                $note->lines()->delete();
            }

            foreach ($lines as $position => $line) {
                $note->lines()->create([...$line, 'position' => $position + 1]);
            }

            return $note;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function incoming(User $actor, Company $company, array $data): array
    {
        $customer = Customer::query()->findOrFail((int) $data['customer_id']);
        if ($customer->company_id !== $company->id) {
            throw ValidationException::withMessages(['customer_id' => __('b2b.customer_not_member')]);
        }

        $branch = Branch::query()->findOrFail((int) $data['branch_id']);
        if (! $actor->canAccessBranch($branch)) {
            abort(404);
        }

        $departmentId = $data['company_department_id'] ?? null;
        if ($departmentId !== null && ! $company->departments()->whereKey((int) $departmentId)->exists()) {
            throw ValidationException::withMessages(['company_department_id' => __('orders.department_invalid')]);
        }

        /** @var list<array{item_id: int, service_type_id: int, expected_quantity: int|float|string, counted_quantity: int|float|string}> $input */
        $input = $data['lines'];
        $items = Item::query()->whereIn('id', array_column($input, 'item_id'))->pluck('name', 'id');
        $services = ServiceType::query()->whereIn('id', array_column($input, 'service_type_id'))->pluck('name', 'id');

        $lines = array_map(fn (array $line): array => [
            'item_id' => $line['item_id'],
            'service_type_id' => $line['service_type_id'],
            'item_name' => (string) $items[$line['item_id']],
            'service_name' => (string) $services[$line['service_type_id']],
            'expected_quantity' => $line['expected_quantity'],
            'counted_quantity' => $line['counted_quantity'],
        ], $input);

        return [['branch_id' => $branch->id, 'customer_id' => $customer->id, 'company_department_id' => $departmentId], $lines];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function outgoing(User $actor, Company $company, array $data): array
    {
        $order = Order::query()->visibleTo($actor)->findOrFail((int) $data['order_id']);
        if ($order->company_id !== $company->id) {
            throw ValidationException::withMessages(['order_id' => __('b2b.order_not_company')]);
        }
        if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Completed], true)) {
            throw ApiException::conflict('delivery_note_order_closed');
        }

        $sent = collect((array) ($data['lines'] ?? []))->mapWithKeys(fn (array $line): array => [(int) $line['order_line_id'] => $line['counted_quantity']]);
        $lines = $order->lines()->get()->map(fn (OrderLine $line): array => [
            'item_id' => $line->item_id,
            'service_type_id' => $line->service_type_id,
            'order_line_id' => $line->id,
            'item_name' => $line->item_name,
            'service_name' => $line->service_name,
            // What was sent; the company's count replaces it when it signs.
            'expected_quantity' => $sent[$line->id] ?? $line->quantity,
            'counted_quantity' => $sent[$line->id] ?? $line->quantity,
        ])->values()->all();
        /** @var list<array<string, mixed>> $lines */

        return [[
            'branch_id' => $order->branch_id,
            'customer_id' => $order->customer_id,
            'company_department_id' => $order->company_department_id,
            'order_id' => $order->id,
        ], $lines];
    }
}
