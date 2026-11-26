<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\UpdatePrices;
use App\Modules\Catalog\Http\Requests\ImportPricesRequest;
use App\Modules\Catalog\Http\Requests\UpdatePricesRequest;
use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Support\PriceSheet;
use App\Modules\Organization\Support\Settings;
use App\Support\Export\XlsxReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PriceController
{
    /**
     * Show the price matrix
     *
     * Every priced item × service pair of the default price list, in
     * whole rials per unit. A missing pair means the service is not
     * offered for that item.
     *
     * @response array{data: list<array{item_id: int, service_type_id: int, price: int}>}
     */
    public function index(): JsonResponse
    {
        Gate::authorize('catalog.view');

        return response()->json(['data' => $this->matrix()]);
    }

    /**
     * Set prices
     *
     * Changes only the listed cells; send `price: null` to remove one.
     * Returns the whole matrix afterwards.
     *
     * @response array{data: list<array{item_id: int, service_type_id: int, price: int}>}
     */
    public function update(UpdatePricesRequest $request, UpdatePrices $updatePrices): JsonResponse
    {
        /** @var list<array{item_id: int, service_type_id: int, price: int|null}> $cells */
        $cells = $request->validated('prices');

        $updatePrices->handle($request->actor(), $cells);

        return response()->json(['data' => $this->matrix()]);
    }

    /**
     * Download the price list as Excel
     *
     * One row per item, one column per active service, amounts in the
     * display unit; an empty cell means the service is not offered. Edit
     * it and send it back to POST /prices/import.
     */
    public function export(Settings $settings): Response
    {
        Gate::authorize('catalog.view');

        return response($this->sheet($settings)->build(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="price-list.xlsx"',
        ]);
    }

    /**
     * Import prices from Excel
     *
     * Compares an edited price list workbook with the current prices and
     * lists what would change, rows it cannot use (`errors`) and columns
     * it ignored (`warnings`). With `apply`, saves the changes, but only
     * when there are no errors. Emptied cells remove the price.
     *
     * @response array{data: array{changes: list<array{item_id: int, item: string, service_type_id: int, service: string, old: int|null, new: int|null}>, errors: list<array{row: int|null, message: string}>, warnings: list<array{row: int|null, message: string}>, applied: bool}}
     */
    public function import(ImportPricesRequest $request, Settings $settings, UpdatePrices $updatePrices): JsonResponse
    {
        try {
            $rows = XlsxReader::firstSheet((string) $request->file('file')?->getRealPath());
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['file' => __('catalog.sheet.unreadable')]);
        }

        $result = $this->sheet($settings)->compare($rows);
        $applied = false;

        if ($request->boolean('apply') && $result['errors'] === [] && $result['changes'] !== []) {
            $updatePrices->handle($request->actor(), array_map(fn (array $change): array => [
                'item_id' => $change['item_id'],
                'service_type_id' => $change['service_type_id'],
                'price' => $change['new'],
            ], $result['changes']));
            $applied = true;
        }

        return response()->json(['data' => [...$result, 'applied' => $applied]]);
    }

    private function sheet(Settings $settings): PriceSheet
    {
        return new PriceSheet($settings->get('general.currency_unit') === 'toman');
    }

    /**
     * @return list<array{item_id: int, service_type_id: int, price: int}>
     */
    private function matrix(): array
    {
        return array_values(Price::query()
            ->where('price_list_id', PriceList::default()->id)
            ->orderBy('item_id')
            ->orderBy('service_type_id')
            ->get()
            ->map(fn (Price $price): array => [
                'item_id' => $price->item_id,
                'service_type_id' => $price->service_type_id,
                'price' => $price->price,
            ])
            ->all());
    }
}
