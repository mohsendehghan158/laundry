<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\HandOverOrder;
use App\Modules\Orders\Http\Requests\HandOverOrderRequest;
use App\Modules\Orders\Http\Requests\UpdateRackRequest;
use App\Modules\Orders\Http\Resources\OrderResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Piece;
use App\Modules\Organization\Support\Settings;
use App\Support\Text\Digits;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * What the counter does with an order that already exists: find it by
 * scanning, hand it over, note where it is kept, and print it.
 */
class OrderCounterController
{
    private const array DETAIL = ['branch', 'customer', 'creator', 'promoCode', 'lines.upcharges', 'lines.pieces.defects', 'history.user', 'payments.user'];

    /**
     * Find an order by scanning
     *
     * Accepts a ticket number (T1-00042) or a piece's tag code
     * (T1-00042-03), typed with any digits and in any case. Returns the
     * order, plus the id of the piece when a tag was scanned.
     *
     * @response array{data: OrderResource, matched_piece_id: int|null}
     */
    #[QueryParameter('code', 'Ticket number or tag code.', required: true, type: 'string')]
    public function lookup(Request $request, #[CurrentUser] User $actor): JsonResponse
    {
        Gate::authorize('orders.view');

        $code = mb_strtoupper(trim(Digits::toLatin((string) $request->query('code', ''))));
        $piece = $code === '' ? null : Piece::query()->where('tag_code', $code)->first();
        $order = $piece?->order ?? ($code === '' ? null : Order::query()->where('ticket_number', $code)->first());

        abort_if($order === null || ! $actor->can('view', $order), 404);

        return response()->json([
            'data' => (new OrderResource($order->load(self::DETAIL)->loadCount('pieces')))->resolve($request),
            'matched_piece_id' => $piece?->id,
        ]);
    }

    /**
     * Hand an order over
     *
     * Marks the chosen ready pieces as given back; the order is completed
     * when none are left. Fails with 409 `handover_unpaid` while money is
     * owed (unless the user has `orders.handover_unpaid`),
     * `handover_not_ready` before the order is ready, and
     * `handover_piece_unavailable` for pieces not ready or not in it.
     */
    public function handover(HandOverOrderRequest $request, Order $order, HandOverOrder $handOver): OrderResource
    {
        Gate::authorize('view', $order);

        /** @var list<int> $pieceIds */
        $pieceIds = array_map('intval', $request->validated('piece_ids'));
        $order = $handOver->handle($request->actor(), $order, $pieceIds);

        return new OrderResource($order->load(self::DETAIL)->loadCount('pieces'));
    }

    /**
     * Set where an order is kept
     */
    public function rack(UpdateRackRequest $request, Order $order): OrderResource
    {
        Gate::authorize('update', $order);

        $order->update(['rack_location' => $request->validated('rack_location'), 'version' => $order->version + 1]);

        return new OrderResource($order->load(self::DETAIL)->loadCount('pieces'));
    }

    /**
     * Show receipt settings
     *
     * Texts and details printed on receipts and tags, for anyone who can
     * see orders (settings themselves need settings.view).
     *
     * @response array{data: array{organization_name: string, header_text: string|null, footer_text: string|null, currency_unit: 'toman'|'rial', prices_include_vat: bool, vat_percent: float}}
     */
    public function receiptSettings(#[CurrentUser] User $actor, Settings $settings): JsonResponse
    {
        Gate::authorize('orders.view');

        return response()->json(['data' => [
            'organization_name' => $actor->organization()->firstOrFail()->name,
            'header_text' => $settings->get('receipt.header_text'),
            'footer_text' => $settings->get('receipt.footer_text'),
            'currency_unit' => $settings->get('general.currency_unit'),
            'prices_include_vat' => (bool) $settings->get('tax.prices_include_vat'),
            'vat_percent' => (float) $settings->get('tax.vat_percent'),
        ]]);
    }
}
