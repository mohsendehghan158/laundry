<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Piece;
use App\Modules\Production\Actions\AssembleOrder;
use App\Modules\Production\Http\Requests\AssemblyScanRequest;
use App\Modules\Production\Http\Requests\CompleteAssemblyRequest;
use App\Modules\Production\Models\RackSlot;
use App\Modules\Production\Support\Assembly;
use App\Modules\Production\Support\Tags;
use App\Support\Exceptions\ApiException;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AssemblyController
{
    /**
     * Show an order at the assembly table
     *
     * Its pieces (gathered, waiting and why), counts, whether it can be
     * completed and the suggested rack slot.
     *
     * @response array{data: array{order: array{id: int, ticket_number: string, status: string, due_at: string, is_express: bool, is_overdue: bool, customer_name: string, branch: array{id: int, name: string}, notes: string|null, rack_location: string|null}, pieces: list<array{id: int, tag_code: string, item_name: string, color_name: string|null, status: string, flag: string|null, flag_note: string|null, branch: array{id: int, name: string}|null, slot: string|null, blocker: string|null}>, counts: array{total: int, assembled: int, ready: int, waiting: int, lost: int}, complete: bool, suggestion: array{id: int, code: string}|null, scan_required: bool}}
     */
    public function show(Order $order, Assembly $assembly, #[CurrentUser] User $actor): JsonResponse
    {
        $this->authorize($actor, $order);

        return response()->json(['data' => $assembly->state($order)]);
    }

    /**
     * Gather a piece
     *
     * Scanned at the assembly table: the piece joins its order's pile and
     * the order is returned as at GET /assembly/{order}, with the id of
     * the scanned piece. Fails with 409 `assembly_piece_blocked` (held,
     * away, in rework or at another branch) or `assembly_piece_done`.
     *
     * @response array{data: array{order: array{id: int, ticket_number: string, status: string, due_at: string, is_express: bool, is_overdue: bool, customer_name: string, branch: array{id: int, name: string}, notes: string|null, rack_location: string|null}, pieces: list<array{id: int, tag_code: string, item_name: string, color_name: string|null, status: string, flag: string|null, flag_note: string|null, branch: array{id: int, name: string}|null, slot: string|null, blocker: string|null}>, counts: array{total: int, assembled: int, ready: int, waiting: int, lost: int}, complete: bool, suggestion: array{id: int, code: string}|null, scan_required: bool}, scanned_piece_id: int}
     */
    public function scan(AssemblyScanRequest $request, AssembleOrder $assemble, Assembly $assembly): JsonResponse
    {
        $piece = Piece::query()->where('tag_code', Tags::normalize((string) $request->validated('tag')))->first();

        if ($piece === null) {
            throw new ApiException('piece_not_found', __('production.errors.piece_not_found'), 404);
        }
        $this->authorize($request->actor(), $piece->order);

        $assemble->piece($request->actor(), $piece);

        return response()->json(['data' => $assembly->state($piece->order->refresh()), 'scanned_piece_id' => $piece->id]);
    }

    /**
     * Complete assembly and rack the order
     *
     * Racks the gathered pieces in a slot of the order's branch (or a
     * free-text place). The order becomes ready when no piece is left in
     * work; otherwise this fails with 409 `assembly_incomplete`, unless
     * `partial` is set, which makes the order partially ready. Pieces can
     * be ticked off here (`piece_ids`) unless the organization requires
     * scanning them.
     *
     * @response array{data: array{order: array{id: int, ticket_number: string, status: string, due_at: string, is_express: bool, is_overdue: bool, customer_name: string, branch: array{id: int, name: string}, notes: string|null, rack_location: string|null}, pieces: list<array{id: int, tag_code: string, item_name: string, color_name: string|null, status: string, flag: string|null, flag_note: string|null, branch: array{id: int, name: string}|null, slot: string|null, blocker: string|null}>, counts: array{total: int, assembled: int, ready: int, waiting: int, lost: int}, complete: bool, suggestion: array{id: int, code: string}|null, scan_required: bool}}
     */
    public function complete(CompleteAssemblyRequest $request, Order $order, AssembleOrder $assemble, Assembly $assembly): JsonResponse
    {
        $this->authorize($request->actor(), $order);

        $slot = $request->filled('rack_slot_id') ? RackSlot::query()->with('rack')->findOrFail($request->integer('rack_slot_id')) : null;
        if ($slot !== null && ($slot->rack->branch_id !== $order->branch_id || $slot->rack->trashed() || ! $slot->rack->is_active)) {
            throw ValidationException::withMessages(['rack_slot_id' => __('production.rack_slot_other_branch')]);
        }

        /** @var list<int> $pieceIds */
        $pieceIds = array_map('intval', $request->validated('piece_ids') ?? []);
        $order = $assemble->complete($request->actor(), $order, $pieceIds, $slot, $request->validated('rack_location'), $request->boolean('partial'));

        return response()->json(['data' => $assembly->state($order)]);
    }

    private function authorize(User $actor, Order $order): void
    {
        abort_unless($actor->hasAllBranchAccess() || $actor->branches()->whereKey($order->branch_id)->exists(), 404);
    }
}
