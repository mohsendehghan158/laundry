<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Piece;
use App\Modules\Organization\Models\Branch;
use App\Modules\Production\Actions\MoveTransfer;
use App\Modules\Production\Concerns\ReachesBranches;
use App\Modules\Production\Http\Requests\StoreTransferRequest;
use App\Modules\Production\Http\Requests\TagsRequest;
use App\Modules\Production\Http\Resources\TransferResource;
use App\Modules\Production\Models\Transfer;
use App\Modules\Production\Support\Board;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TransferController
{
    use ReachesBranches;

    private const array DETAIL = ['fromBranch', 'toBranch', 'creator', 'sender', 'receiver', 'pieces.line', 'pieces.order'];

    /**
     * List transfers
     *
     * Transfers from or to the user's branches, newest first: the open
     * and in-transit ones, or all with `status`.
     */
    #[QueryParameter('status', 'open, sent, received or cancelled; default: open and sent.', type: 'string')]
    public function index(Request $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        Gate::authorize('production.transfer');
        $request->validate(['status' => ['nullable', Rule::in(['open', 'sent', 'received', 'cancelled'])]]);
        $mine = $this->branchIds($actor);

        $transfers = Transfer::query()
            ->with(['fromBranch', 'toBranch', 'creator', 'sender', 'receiver'])
            ->withCount(['pieces', 'pieces as received_count' => fn ($query) => $query->whereNotNull('transfer_pieces.received_at')])
            ->when($mine !== null, fn ($query) => $query->where(fn ($q) => $q->whereIn('from_branch_id', $mine)->orWhereIn('to_branch_id', $mine)))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')), fn ($query) => $query->whereIn('status', ['open', 'sent']))
            ->latest('id')
            ->limit(100)
            ->get();

        return TransferResource::collection($transfers);
    }

    /**
     * Where transfers can go
     *
     * Every active branch of the organization (a counter sends to the
     * plant even when its staff do not work there).
     *
     * @response array{data: list<array{id: int, name: string, type: string}>}
     */
    public function destinations(): JsonResponse
    {
        Gate::authorize('production.transfer');

        return response()->json(['data' => Branch::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (Branch $branch): array => ['id' => $branch->id, 'name' => $branch->name, 'type' => $branch->type->value])
            ->values()->all()]);
    }

    /**
     * Start a transfer
     *
     * An open transfer at the sending branch; scan pieces onto it, then send it.
     */
    public function store(StoreTransferRequest $request, MoveTransfer $move): JsonResponse
    {
        $from = Branch::query()->findOrFail($request->integer('from_branch_id'));
        abort_unless($request->actor()->canAccessBranch($from), 404);
        $to = Branch::query()->findOrFail($request->integer('to_branch_id'));

        $transfer = $move->create($request->actor(), $from, $to, $request->validated('note'));

        return (new TransferResource($transfer->load(self::DETAIL)))->response()->setStatusCode(201);
    }

    /**
     * Show a transfer
     *
     * With its pieces (the manifest) and which have been checked in.
     */
    public function show(Transfer $transfer, #[CurrentUser] User $actor): TransferResource
    {
        $this->reach($actor, $transfer);

        return new TransferResource($transfer->load(self::DETAIL));
    }

    /**
     * Add pieces to a transfer
     *
     * Scanned at the sending branch, tag by tag: pieces must be in work
     * there, not held, away or lost, and not on another open transfer.
     *
     * @response array{data: TransferResource, results: list<array{tag: string, ok: bool, message: string|null}>}
     */
    public function addPieces(TagsRequest $request, Transfer $transfer, MoveTransfer $move): JsonResponse
    {
        $this->reach($request->actor(), $transfer, sending: true);
        $results = $move->add($transfer, $request->tags());

        return response()->json(['data' => (new TransferResource($transfer->refresh()->load(self::DETAIL)))->resolve($request), 'results' => $results]);
    }

    /**
     * Take a piece off an open transfer
     */
    public function removePiece(Transfer $transfer, Piece $piece, MoveTransfer $move, #[CurrentUser] User $actor): TransferResource
    {
        $this->reach($actor, $transfer, sending: true);
        $move->remove($transfer, $piece);

        return new TransferResource($transfer->refresh()->load(self::DETAIL));
    }

    /**
     * Send a transfer
     *
     * Its pieces are in transit until checked in at the other branch.
     */
    public function send(Request $request, Transfer $transfer, MoveTransfer $move, #[CurrentUser] User $actor): TransferResource
    {
        $this->reach($actor, $transfer, sending: true);
        /** @var array{bags: int} $input */
        $input = $request->validate(['bags' => ['required', 'integer', 'min:1', 'max:500']]);

        $transfer = $move->send($actor, $transfer, $input['bags']);
        Board::changed([$transfer->from_branch_id, $transfer->to_branch_id], 'transfer');

        return new TransferResource($transfer->refresh()->load(self::DETAIL));
    }

    /**
     * Check pieces in
     *
     * Scanned at the receiving branch as they come out of the bags.
     *
     * @response array{data: TransferResource, results: list<array{tag: string, ok: bool, message: string|null}>}
     */
    public function receive(TagsRequest $request, Transfer $transfer, MoveTransfer $move): JsonResponse
    {
        $this->reach($request->actor(), $transfer, receiving: true);
        $results = $move->receive($request->actor(), $transfer, $request->tags());
        Board::changed([$transfer->to_branch_id], 'transfer');

        return response()->json(['data' => (new TransferResource($transfer->refresh()->load(self::DETAIL)))->resolve($request), 'results' => $results]);
    }

    /**
     * Close a transfer
     *
     * Records the bags counted at arrival. With `all`, pieces not scanned
     * in are checked in too; otherwise they stay in transit as missing.
     */
    public function close(Request $request, Transfer $transfer, MoveTransfer $move, #[CurrentUser] User $actor): TransferResource
    {
        $this->reach($actor, $transfer, receiving: true);
        /** @var array{bags?: int|null, all?: bool} $input */
        $input = $request->validate(['bags' => ['nullable', 'integer', 'min:0', 'max:500'], 'all' => ['boolean']]);

        $transfer = $move->close($actor, $transfer, $input['bags'] ?? null, (bool) ($input['all'] ?? false));
        Board::changed([$transfer->to_branch_id], 'transfer');

        return new TransferResource($transfer->refresh()->load(self::DETAIL));
    }

    /**
     * Cancel an open transfer
     */
    public function destroy(Transfer $transfer, MoveTransfer $move, #[CurrentUser] User $actor): Response
    {
        $this->reach($actor, $transfer, sending: true);
        $move->cancel($transfer);

        return response()->noContent();
    }

    private function reach(User $actor, Transfer $transfer, bool $sending = false, bool $receiving = false): void
    {
        abort_unless($actor->can('production.transfer'), 403);
        $branches = $sending ? [$transfer->from_branch_id] : ($receiving ? [$transfer->to_branch_id] : [$transfer->from_branch_id, $transfer->to_branch_id]);
        abort_unless($this->inBranches($actor, ...$branches), 404);
    }
}
