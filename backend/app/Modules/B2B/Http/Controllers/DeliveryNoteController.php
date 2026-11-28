<?php

declare(strict_types=1);

namespace App\Modules\B2B\Http\Controllers;

use App\Modules\B2B\Actions\ConfirmDeliveryNote;
use App\Modules\B2B\Actions\SaveDeliveryNote;
use App\Modules\B2B\Enums\DeliveryNoteStatus;
use App\Modules\B2B\Http\Requests\ConfirmDeliveryNoteRequest;
use App\Modules\B2B\Http\Requests\SaveDeliveryNoteRequest;
use App\Modules\B2B\Http\Resources\DeliveryNoteResource;
use App\Modules\B2B\Models\DeliveryNote;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\ApiException;
use App\Support\Http\PerPage;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DeliveryNoteController
{
    private const array DETAIL = ['company', 'department', 'branch', 'customer', 'order', 'creator', 'lines'];

    /**
     * List delivery notes
     *
     * Newest first, for the branches the user works in.
     */
    #[QueryParameter('filter[company_id]', type: 'integer')]
    #[QueryParameter('filter[order_id]', type: 'integer')]
    #[QueryParameter('filter[type]', 'in or out.', type: 'string')]
    #[QueryParameter('filter[status]', 'draft or confirmed.', type: 'string')]
    #[QueryParameter('page', type: 'integer', default: 1)]
    #[QueryParameter('per_page', 'Page size, 1-100.', type: 'integer', default: 25)]
    public function index(Request $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $this->authorizeView($actor);

        $notes = $this->visible($actor)
            ->with(self::DETAIL)
            ->when($request->input('filter.company_id'), fn (Builder $query, mixed $id) => $query->where('company_id', (int) $id))
            ->when($request->input('filter.order_id'), fn (Builder $query, mixed $id) => $query->where('order_id', (int) $id))
            ->when($request->input('filter.type'), fn (Builder $query, mixed $type) => $query->where('type', (string) $type))
            ->when($request->input('filter.status'), fn (Builder $query, mixed $status) => $query->where('status', (string) $status))
            ->latest('id')
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return DeliveryNoteResource::collection($notes);
    }

    /**
     * Show a delivery note
     */
    public function show(int $deliveryNote, #[CurrentUser] User $actor): DeliveryNoteResource
    {
        $this->authorizeView($actor);

        return new DeliveryNoteResource($this->visible($actor)->with(self::DETAIL)->findOrFail($deliveryNote));
    }

    /**
     * Record a delivery note
     *
     * As a draft. In: items a company sent, with what it stated and what
     * was counted. Out: an order of the company going back.
     */
    public function store(SaveDeliveryNoteRequest $request, SaveDeliveryNote $saveDeliveryNote): JsonResponse
    {
        $note = $saveDeliveryNote->handle($request->actor(), $request->validated());

        return $this->show($note->id, $request->actor())->response()->setStatusCode(201);
    }

    /**
     * Change a draft delivery note
     *
     * Fails with 409 `delivery_note_confirmed` once confirmed.
     */
    public function update(SaveDeliveryNoteRequest $request, int $deliveryNote, SaveDeliveryNote $saveDeliveryNote): DeliveryNoteResource
    {
        $note = $this->visible($request->actor())->findOrFail($deliveryNote);
        $saveDeliveryNote->handle($request->actor(), $request->validated(), $note);

        return $this->show($note->id, $request->actor());
    }

    /**
     * Confirm a delivery note
     *
     * With who signed for the company. In: makes the order of the counted
     * items. Out: records the company's count and hands the order over
     * (409 `handover_not_ready` until it is ready).
     */
    public function confirm(ConfirmDeliveryNoteRequest $request, int $deliveryNote, ConfirmDeliveryNote $confirmDeliveryNote): DeliveryNoteResource
    {
        $note = $this->visible($request->actor())->findOrFail($deliveryNote);
        $confirmDeliveryNote->handle($request->actor(), $note, (string) $request->validated('counterpart_name'), $request->received());

        return $this->show($note->id, $request->actor());
    }

    /**
     * Delete a draft delivery note
     */
    public function destroy(int $deliveryNote, #[CurrentUser] User $actor): Response
    {
        abort_unless($actor->can('companies.delivery'), 403);
        $note = $this->visible($actor)->findOrFail($deliveryNote);

        if ($note->status !== DeliveryNoteStatus::Draft) {
            throw ApiException::conflict('delivery_note_confirmed');
        }

        $note->delete();

        return response()->noContent();
    }

    private function authorizeView(User $actor): void
    {
        abort_unless($actor->canAny(['companies.view', 'companies.delivery']), 403);
    }

    /**
     * @return Builder<DeliveryNote>
     */
    private function visible(User $actor): Builder
    {
        return DeliveryNote::query()->when(! $actor->hasAllBranchAccess(), fn (Builder $query) => $query->whereIn('branch_id', $actor->branches()->select('branches.id')));
    }
}
