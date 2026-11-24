<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Http\Requests\PreviewOrderRequest;
use App\Modules\Orders\Services\OrderPricer;
use Illuminate\Http\JsonResponse;

class OrderPreviewController
{
    /**
     * Preview an order's price
     *
     * Runs the same pricing as taking the order in, without saving
     * anything, so the counter can show the total and due time while the
     * order is being put together. Amounts are whole rials.
     *
     * @response array{data: array{lines: list<array{item_name: string, service_name: string, quantity: float, unit_price: int, base_amount: int, upcharge_amount: int, surcharge_amount: int, line_total: int, piece_count: int}>, turnaround: array{id: int|null, name: string|null, surcharge_percent: int}, subtotal: int, discount_amount: int, tax_amount: int, total: int, piece_count: int, due_at: string}}
     */
    public function __invoke(PreviewOrderRequest $request, OrderPricer $pricer): JsonResponse
    {
        /** @var array{lines: list<array<string, mixed>>, turnaround_tier_id?: int|null, discount?: array{type: string, value: int|float|string}|null} $input */
        $input = $request->validated();
        $priced = $pricer->price($input);

        return response()->json(['data' => [
            'lines' => array_map(fn (array $line): array => [
                'item_name' => $line['item_name'],
                'service_name' => $line['service_name'],
                'quantity' => (float) $line['quantity'],
                'unit_price' => $line['unit_price'],
                'base_amount' => $line['base_amount'],
                'upcharge_amount' => $line['upcharge_amount'],
                'surcharge_amount' => $line['surcharge_amount'],
                'line_total' => $line['line_total'],
                'piece_count' => $line['piece_count'],
            ], $priced['lines']),
            'turnaround' => ['id' => $priced['tier']?->id, 'name' => $priced['tier']?->name, 'surcharge_percent' => $priced['surcharge_percent']],
            'subtotal' => $priced['subtotal'],
            'discount_amount' => $priced['discount_amount'],
            'tax_amount' => $priced['tax_amount'],
            'total' => $priced['total'],
            'piece_count' => array_sum(array_column($priced['lines'], 'piece_count')),
            'due_at' => $pricer->dueAt(now(), $priced['duration_hours'])->toIso8601String(),
        ]]);
    }
}
