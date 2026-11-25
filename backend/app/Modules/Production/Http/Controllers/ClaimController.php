<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Piece;
use App\Modules\Production\Actions\HandleClaim;
use App\Modules\Production\Concerns\ReachesBranches;
use App\Modules\Production\Enums\ClaimKind;
use App\Modules\Production\Http\Requests\OpenClaimRequest;
use App\Modules\Production\Http\Requests\SettleClaimRequest;
use App\Modules\Production\Http\Resources\ClaimResource;
use App\Modules\Production\Models\Claim;
use App\Modules\Production\Support\Board;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ClaimController
{
    use ReachesBranches;

    private const array DETAIL = ['order.customer', 'piece.line', 'creator', 'resolver'];

    /**
     * List damage and loss claims
     *
     * Of the user's branches' orders, newest first: open ones, or all with `status`.
     */
    #[QueryParameter('status', 'open, resolved or rejected; default: open.', type: 'string')]
    public function index(Request $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        abort_unless($actor->can('claims.view'), 403);
        $request->validate(['status' => ['nullable', Rule::in(['open', 'resolved', 'rejected'])]]);
        $mine = $this->branchIds($actor);

        $claims = Claim::query()
            ->with(self::DETAIL)
            ->when($mine !== null, fn ($query) => $query->whereHas('order', fn ($q) => $q->whereIn('branch_id', $mine)))
            ->where('status', $request->input('status', 'open'))
            ->latest('id')
            ->limit(200)
            ->get();

        return ClaimResource::collection($claims);
    }

    /**
     * Open a claim
     *
     * Against a piece: `lost` flags it lost (it no longer holds its order
     * back); `damaged` flags it damaged (it is still handed back). Fails
     * with 409 `claim_already_open` for a second open claim of a kind.
     */
    public function store(OpenClaimRequest $request, HandleClaim $claims): JsonResponse
    {
        $piece = Piece::query()->with('order')->findOrFail($request->integer('piece_id'));
        abort_unless($this->reachesPiece($request->actor(), $piece), 404);

        $claim = $claims->open($request->actor(), $piece, ClaimKind::from((string) $request->validated('kind')), (string) $request->validated('description'));
        Board::changed([$piece->branch_id], 'claim', $piece->order_id);

        return (new ClaimResource($claim->load(self::DETAIL)))->response()->setStatusCode(201);
    }

    /**
     * Resolve a claim
     *
     * Settled with the customer; record how and any compensation paid.
     */
    public function resolve(SettleClaimRequest $request, Claim $claim, HandleClaim $claims): ClaimResource
    {
        $this->reach($request->actor(), $claim);
        $compensation = $request->validated('compensation');
        $claims->resolve($request->actor(), $claim, (string) $request->validated('resolution'), $compensation === null ? null : (int) $compensation);

        return new ClaimResource($claim->refresh()->load(self::DETAIL));
    }

    /**
     * Reject a claim
     *
     * Not upheld; a lost piece rejected this way has been found and goes back to work.
     */
    public function reject(SettleClaimRequest $request, Claim $claim, HandleClaim $claims): ClaimResource
    {
        $this->reach($request->actor(), $claim);
        $claims->reject($request->actor(), $claim, (string) $request->validated('resolution'));
        Board::changed([$claim->piece->branch_id], 'claim', $claim->order_id);

        return new ClaimResource($claim->refresh()->load(self::DETAIL));
    }

    private function reach(User $actor, Claim $claim): void
    {
        abort_unless($this->reachesPiece($actor, $claim->piece()->with('order')->firstOrFail()), 404);
    }
}
