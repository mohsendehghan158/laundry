<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Piece;
use App\Modules\Production\Actions\HoldPiece;
use App\Modules\Production\Concerns\ReachesBranches;
use App\Modules\Production\Http\Requests\HoldPieceRequest;
use App\Modules\Production\Http\Resources\PieceDetailResource;
use App\Modules\Production\Support\Board;
use App\Modules\Production\Support\Tags;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;

class PieceController
{
    use ReachesBranches;

    /**
     * Find a piece by its tag
     *
     * Where the piece is (branch, station, rack), its state and side flag,
     * its order and its full history. Tags may be typed with any digits
     * and in any case.
     */
    public function show(string $tag, #[CurrentUser] User $actor): PieceDetailResource
    {
        abort_unless($actor->can('production.view') || $actor->can('orders.view'), 403);

        $piece = Piece::query()->where('tag_code', Tags::normalize($tag))->first();
        abort_if($piece === null || ! $this->reachesPiece($actor, $piece), 404);

        return new PieceDetailResource($this->load($piece));
    }

    /**
     * Put a piece on hold
     *
     * A held piece cannot be scanned or gathered until released.
     */
    public function hold(HoldPieceRequest $request, Piece $piece, HoldPiece $holdPiece): PieceDetailResource
    {
        abort_unless($this->reachesPiece($request->actor(), $piece), 404);
        $holdPiece->hold($request->actor(), $piece, (string) $request->validated('reason'));
        Board::changed([$piece->branch_id], 'hold', $piece->order_id);

        return new PieceDetailResource($this->load($piece));
    }

    /**
     * Release a held piece
     */
    public function release(Piece $piece, HoldPiece $holdPiece, #[CurrentUser] User $actor): PieceDetailResource
    {
        Gate::authorize('production.scan');
        abort_unless($this->reachesPiece($actor, $piece), 404);
        $holdPiece->release($actor, $piece);
        Board::changed([$piece->branch_id], 'hold', $piece->order_id);

        return new PieceDetailResource($this->load($piece));
    }

    private function load(Piece $piece): Piece
    {
        return $piece->refresh()->load(['order.customer', 'order.branch', 'line', 'defects', 'branch', 'station', 'events.station', 'events.branch', 'events.user', 'photos.uploader', 'claims'])
            ->setRelation('order', $piece->order->loadCount('pieces'));
    }
}
