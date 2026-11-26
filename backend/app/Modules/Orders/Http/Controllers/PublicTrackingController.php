<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderStatusChange;
use App\Modules\Organization\Support\Settings;
use App\Modules\Organization\Tenancy\OrganizationContext;
use Illuminate\Http\JsonResponse;

class PublicTrackingController
{
    /**
     * Track an order (public)
     *
     * For the link in the customer's SMS and on their receipt. Needs no
     * sign-in; the id is the order's unguessable public id. Shows where
     * the order is and what is owed, but nothing personal: no name, no
     * phone number, no item details.
     *
     * @unauthenticated
     *
     * @response array{data: array{ticket_number: string, status: string, organization: string, branch: array{name: string, phone: string|null, address: string|null}, received_at: string|null, due_at: string, ready_at: string|null, completed_at: string|null, piece_count: int, total: int, paid_amount: int, balance: int, currency_unit: 'toman'|'rial', history: list<array{status: string, at: string}>}}
     */
    public function __invoke(string $publicId, OrganizationContext $context, Settings $settings): JsonResponse
    {
        // There is no signed-in user, so look across organizations by the unguessable id.
        $order = Order::query()
            ->withoutGlobalScopes()
            ->where('public_id', strtolower($publicId))
            ->with(['branch', 'history', 'organization'])
            ->withCount('pieces')
            ->first();

        abort_if($order === null, 404);

        $currencyUnit = $context->runFor($order->organization_id, fn (): mixed => $settings->get('general.currency_unit'));

        return response()->json(['data' => [
            'ticket_number' => $order->ticket_number,
            'status' => $order->status->value,
            'organization' => $order->organization->name,
            'branch' => ['name' => $order->branch->name, 'phone' => $order->branch->phone, 'address' => $order->branch->address],
            'received_at' => $order->received_at?->toIso8601String(),
            'due_at' => $order->due_at->toIso8601String(),
            'ready_at' => $order->ready_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'piece_count' => (int) $order->getAttribute('pieces_count'),
            'total' => $order->total,
            'paid_amount' => $order->paid_amount,
            'balance' => $order->balance(),
            'currency_unit' => $currencyUnit,
            'history' => $order->history->map(fn (OrderStatusChange $change): array => [
                'status' => $change->to_status->value,
                'at' => $change->created_at->toIso8601String(),
            ])->all(),
        ]]);
    }
}
