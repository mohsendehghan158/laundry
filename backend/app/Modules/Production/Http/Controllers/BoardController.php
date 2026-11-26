<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PieceStatus;
use App\Modules\Orders\Models\Piece;
use App\Modules\Organization\Models\Branch;
use App\Modules\Production\Enums\PieceFlag;
use App\Modules\Production\Models\Station;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class BoardController
{
    /**
     * Production board
     *
     * The pieces in work at a branch, by station: a column for pieces not
     * yet scanned, one per active station in workflow order, and one for
     * pieces gathered and waiting for the rack. Each column lists orders,
     * overdue first, then express, then by due time. Counters cover the
     * whole branch. Boards reload when told to on the private channel
     * `plant.{branch_id}.board`.
     *
     * @response array{data: array{branch: array{id: int, name: string}, counters: array{pieces: int, orders: int, due_today: int, overdue: int, express: int, rework: int, on_hold: int}, columns: list<array{key: string, station: array{id: int, name: string, kind: string}|null, pieces: int, orders: list<array{id: int, ticket_number: string, customer_name: string, pieces: int, total_pieces: int, due_at: string, is_express: bool, is_overdue: bool, rework: int, on_hold: int}>}>, generated_at: string}}
     */
    #[QueryParameter('branch_id', 'The branch (usually a plant) to show.', required: true, type: 'int')]
    public function show(Request $request, #[CurrentUser] User $actor): JsonResponse
    {
        Gate::authorize('production.view');
        $branch = Branch::query()->find($request->integer('branch_id'));
        abort_if($branch === null || ! $actor->canAccessBranch($branch), 404);

        $stations = Station::query()->where('branch_id', $branch->id)->where('is_active', true)->ordered()->get();
        $pieces = Piece::query()
            ->with(['order.customer'])
            ->where('branch_id', $branch->id)
            ->whereIn('status', [PieceStatus::Received, PieceStatus::InProcess, PieceStatus::Assembled])
            ->where(fn ($query) => $query->whereNull('flag')->orWhereNotIn('flag', [PieceFlag::Outsourced, PieceFlag::InTransit, PieceFlag::Lost]))
            ->whereHas('order', fn ($query) => $query->whereIn('status', [OrderStatus::Received, OrderStatus::InProcess, OrderStatus::PartiallyReady]))
            ->get();
        $totals = Piece::query()->whereIn('order_id', $pieces->pluck('order_id')->unique())->selectRaw('order_id, count(*) as total')->groupBy('order_id')->pluck('total', 'order_id');

        $stationIds = $stations->pluck('id')->all();
        $column = fn (Piece $piece): string => match (true) {
            $piece->status === PieceStatus::Assembled => 'assembled',
            $piece->status === PieceStatus::InProcess && in_array($piece->station_id, $stationIds, true) => 'station-'.$piece->station_id,
            default => 'waiting',
        };
        $grouped = $pieces->groupBy($column);

        $columns = [['key' => 'waiting', 'station' => null]];
        foreach ($stations as $station) {
            $columns[] = ['key' => 'station-'.$station->id, 'station' => ['id' => $station->id, 'name' => $station->name, 'kind' => $station->kind->value]];
        }
        $columns[] = ['key' => 'assembled', 'station' => null];

        $orders = $pieces->groupBy('order_id');
        $today = now()->endOfDay();

        return response()->json(['data' => [
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'counters' => [
                'pieces' => $pieces->count(),
                'orders' => $orders->count(),
                'due_today' => $orders->filter(fn (Collection $group): bool => $group->firstOrFail()->order->due_at->lte($today) && ! $group->firstOrFail()->order->due_at->isPast())->count(),
                'overdue' => $orders->filter(fn (Collection $group): bool => $group->firstOrFail()->order->due_at->isPast())->count(),
                'express' => $orders->filter(fn (Collection $group): bool => $group->firstOrFail()->order->surcharge_percent > 0)->count(),
                'rework' => $pieces->where('flag', PieceFlag::Rework)->count(),
                'on_hold' => $pieces->where('flag', PieceFlag::OnHold)->count(),
            ],
            'columns' => array_map(fn (array $definition): array => [
                ...$definition,
                'pieces' => ($grouped[$definition['key']] ?? collect())->count(),
                'orders' => $this->cards($grouped[$definition['key']] ?? collect(), $totals),
            ], $columns),
            'generated_at' => now()->toIso8601String(),
        ]]);
    }

    /**
     * @param  Collection<int, Piece>  $pieces
     * @param  Collection<int, int>  $totals
     * @return list<array{id: int, ticket_number: string, customer_name: string, pieces: int, total_pieces: int, due_at: string, is_express: bool, is_overdue: bool, rework: int, on_hold: int}>
     */
    private function cards(Collection $pieces, Collection $totals): array
    {
        return array_values($pieces->groupBy('order_id')
            ->map(function (Collection $group) use ($totals): array {
                $order = $group->firstOrFail()->order;

                return [
                    'id' => $order->id,
                    'ticket_number' => $order->ticket_number,
                    'customer_name' => $order->customer->name,
                    'pieces' => $group->count(),
                    'total_pieces' => (int) ($totals[$order->id] ?? $group->count()),
                    'due_at' => $order->due_at->toIso8601String(),
                    'is_express' => $order->surcharge_percent > 0,
                    'is_overdue' => $order->due_at->isPast(),
                    'rework' => $group->where('flag', PieceFlag::Rework)->count(),
                    'on_hold' => $group->where('flag', PieceFlag::OnHold)->count(),
                ];
            })
            ->sortBy([['is_overdue', 'desc'], ['is_express', 'desc'], ['due_at', 'asc']])
            ->all());
    }
}
