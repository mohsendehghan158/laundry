<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemCategory;
use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\ServiceType;
use App\Support\Export\XlsxWriter;
use App\Support\Text\Digits;
use Illuminate\Support\Collection;

/**
 * The price list as a spreadsheet: one row per item, one column per active
 * service, amounts in the display unit and an empty cell where a service
 * is not offered. The same layout is read back on import; items are
 * matched by their id column, services by the code in their header.
 *
 * @phpstan-type PriceChange array{item_id: int, item: string, service_type_id: int, service: string, old: int|null, new: int|null}
 * @phpstan-type SheetProblem array{row: int|null, message: string}
 */
final class PriceSheet
{
    private const int RIAL_PER_TOMAN = 10;

    public function __construct(private readonly bool $toman) {}

    public function build(): string
    {
        $services = $this->services();
        $prices = $this->current();
        $categories = ItemCategory::query()->pluck('name', 'id');

        $headers = [__('catalog.sheet.id'), __('catalog.sheet.category'), __('catalog.sheet.item'), __('catalog.sheet.unit')];
        foreach ($services as $service) {
            $headers[] = "{$service->name} [{$service->code}]";
        }

        $rows = $this->items()->map(function (Item $item) use ($services, $prices, $categories): array {
            $row = [$item->id, (string) ($categories[$item->item_category_id] ?? ''), $item->name, (string) __("catalog.units.{$item->unit->value}")];
            foreach ($services as $service) {
                $price = $prices["{$item->id}:{$service->id}"] ?? null;
                $row[] = $price === null ? null : $this->display($price);
            }

            return $row;
        })->all();

        return XlsxWriter::build(__('catalog.sheet.name'), $headers, array_values($rows));
    }

    /**
     * Compares a filled-in sheet with the current prices.
     *
     * @param  list<list<string|int|float|null>>  $rows
     * @return array{changes: list<PriceChange>, errors: list<SheetProblem>, warnings: list<SheetProblem>}
     */
    public function compare(array $rows): array
    {
        $errors = [];
        $warnings = [];
        $header = array_map(fn (string|int|float|null $cell): string => trim((string) $cell), $rows[0] ?? []);
        $idColumn = array_search(__('catalog.sheet.id'), $header, true);

        if ($idColumn === false) {
            return ['changes' => [], 'errors' => [['row' => 1, 'message' => __('catalog.sheet.no_id_column')]], 'warnings' => []];
        }

        // Which column holds which service.
        $services = ServiceType::query()->get();
        $byCode = $services->keyBy(fn (ServiceType $s): string => mb_strtolower($s->code));
        $byName = $services->keyBy('name');
        $known = [__('catalog.sheet.id'), __('catalog.sheet.category'), __('catalog.sheet.item'), __('catalog.sheet.unit'), ''];
        /** @var array<int, ServiceType> $columns */
        $columns = [];
        foreach ($header as $index => $title) {
            if (in_array($title, $known, true)) {
                continue;
            }
            $service = preg_match('/\[([^\]]+)]\s*$/u', $title, $match) === 1 ? $byCode->get(mb_strtolower(trim($match[1]))) : $byName->get($title);
            if ($service === null) {
                $warnings[] = ['row' => null, 'message' => __('catalog.sheet.unknown_column', ['column' => $title])];

                continue;
            }
            $columns[$index] = $service;
        }

        if ($columns === []) {
            $errors[] = ['row' => 1, 'message' => __('catalog.sheet.no_service_columns')];
        }

        $items = Item::query()->get()->keyBy('id');
        $prices = $this->current();
        $changes = [];
        $seen = [];

        foreach (array_slice($rows, 1, null, true) as $index => $row) {
            $line = $index + 1;
            if (array_filter($row, fn (string|int|float|null $cell): bool => $cell !== null && trim((string) $cell) !== '') === []) {
                continue;
            }

            $id = Digits::toLatin(trim((string) ($row[$idColumn] ?? '')));
            $item = ctype_digit($id) ? $items->get((int) $id) : null;
            if ($item === null) {
                $errors[] = ['row' => $line, 'message' => __('catalog.sheet.unknown_item', ['id' => $id])];

                continue;
            }
            if (isset($seen[$item->id])) {
                $errors[] = ['row' => $line, 'message' => __('catalog.sheet.duplicate_item', ['item' => $item->name, 'row' => $seen[$item->id]])];

                continue;
            }
            $seen[$item->id] = $line;

            foreach ($columns as $column => $service) {
                $amount = $this->amount($row[$column] ?? null);
                if ($amount === false) {
                    $errors[] = ['row' => $line, 'message' => __('catalog.sheet.invalid_amount', ['item' => $item->name, 'service' => $service->name, 'value' => (string) $row[$column]])];

                    continue;
                }

                $old = $prices["{$item->id}:{$service->id}"] ?? null;
                if ($old !== $amount) {
                    $changes[] = ['item_id' => $item->id, 'item' => $item->name, 'service_type_id' => $service->id, 'service' => $service->name, 'old' => $old, 'new' => $amount];
                }
            }
        }

        return ['changes' => $changes, 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * A cell as whole rials: null when empty (not offered), false when it
     * is not a valid amount.
     */
    private function amount(string|int|float|null $cell): int|false|null
    {
        if ($cell === null) {
            return null;
        }

        if (is_string($cell)) {
            $text = str_replace([',', '٬', '،', ' ', "\u{00A0}", "\u{202F}"], '', Digits::toLatinNumber(trim($cell)));
            if ($text === '') {
                return null;
            }
            if (! is_numeric($text)) {
                return false;
            }
            $cell = (float) $text;
        }

        $rial = (int) round($cell * ($this->toman ? self::RIAL_PER_TOMAN : 1));

        return $cell < 0 || $rial > 1_000_000_000_000 ? false : $rial;
    }

    private function display(int $rial): int|float
    {
        if (! $this->toman) {
            return $rial;
        }

        return $rial % self::RIAL_PER_TOMAN === 0 ? intdiv($rial, self::RIAL_PER_TOMAN) : $rial / self::RIAL_PER_TOMAN;
    }

    /**
     * @return Collection<int, ServiceType>
     */
    private function services(): Collection
    {
        return ServiceType::query()->active()->ordered()->get();
    }

    /**
     * Every item, in the order of the price grid in the panel.
     *
     * @return Collection<int, Item>
     */
    private function items(): Collection
    {
        return Item::query()
            ->orderBy(ItemCategory::query()->select('sort_order')->whereColumn('item_categories.id', 'items.item_category_id'))
            ->orderBy('item_category_id')
            ->ordered()
            ->get();
    }

    /**
     * @return array<string, int> "item:service" → rials
     */
    private function current(): array
    {
        return Price::query()
            ->where('price_list_id', PriceList::default()->id)
            ->get()
            ->mapWithKeys(fn (Price $price): array => ["{$price->item_id}:{$price->service_type_id}" => $price->price])
            ->all();
    }
}
