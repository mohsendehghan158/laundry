<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\B2B\Support\CompanyAccounts;
use App\Modules\Billing\Models\PromoCode;
use App\Modules\Billing\Support\PromoCodes;
use App\Modules\Catalog\Enums\PriceUnit;
use App\Modules\Catalog\Models\Color;
use App\Modules\Catalog\Models\DefectType;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\ServiceType;
use App\Modules\Catalog\Models\TurnaroundTier;
use App\Modules\Catalog\Models\Upcharge;
use App\Modules\Orders\Enums\DiscountType;
use App\Modules\Organization\Support\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Prices an order request against the current catalog: looks up items,
 * services, the price matrix, upcharges and the turnaround tier, then
 * runs the price engine. The result holds everything needed to store the
 * order (names and prices are copied so later catalog edits do not change
 * existing orders) or to show a preview.
 *
 * @phpstan-type PricedPiece array{color_id: int|null, color_name: string|null, brand: string|null, notes: string|null, defects: list<array{defect_type_id: int, name: string, kind: string}>}
 * @phpstan-type PricedLine array{item_id: int, service_type_id: int, item_name: string, service_name: string, service_code: string, unit: PriceUnit, quantity: string, unit_price: int, base_amount: int, upcharge_amount: int, surcharge_amount: int, line_total: int, notes: string|null, upcharges: list<array{upcharge_id: int, name: string, type: string, value: int, amount: int}>, piece_count: int, pieces: list<PricedPiece>}
 * @phpstan-type Priced array{lines: list<PricedLine>, tier: TurnaroundTier|null, duration_hours: int, surcharge_percent: int, subtotal: int, discount_type: DiscountType|null, discount_value: string, discount_amount: int, promo: PromoCode|null, tax_amount: int, total: int}
 */
final class OrderPricer
{
    /** Used when the catalog has no turnaround tier at all. */
    public const int FALLBACK_HOURS = 72;

    public function __construct(
        private readonly PriceCalculator $calculator,
        private readonly DueDateCalculator $dueDates,
        private readonly Settings $settings,
        private readonly PromoCodes $promoCodes,
        private readonly CompanyAccounts $companies,
    ) {}

    /**
     * @param  array{lines: list<array<string, mixed>>, turnaround_tier_id?: int|null, discount?: array{type: string, value: int|float|string}|null, promo_code?: string|null, customer_id?: int|null}  $input  validated request data
     * @param  int|null  $orderId  the order being edited, so its own use of a promo code is not counted
     * @return Priced
     */
    public function price(array $input, ?int $orderId = null): array
    {
        $lines = $input['lines'];
        $items = Item::query()->whereIn('id', array_column($lines, 'item_id'))->get()->keyBy('id');
        $services = ServiceType::query()->whereIn('id', array_column($lines, 'service_type_id'))->get()->keyBy('id');
        $upchargeIds = array_merge(...array_map(fn (array $line): array => $line['upcharge_ids'] ?? [], $lines));
        $upcharges = Upcharge::query()->active()->whereIn('id', $upchargeIds)->get()->keyBy('id');
        // A company's contract list replaces the default prices it has; the rest stay.
        $lists = array_values(array_filter([PriceList::default()->id, $this->companies->priceListFor($input['customer_id'] ?? null)]));
        $prices = Price::query()
            ->whereIn('price_list_id', $lists)
            ->whereIn('item_id', $items->keys())
            ->get()
            ->sortBy(fn (Price $price): int => (int) array_search($price->price_list_id, $lists, true))
            ->mapWithKeys(fn (Price $price): array => ["{$price->item_id}:{$price->service_type_id}" => $price->price]);
        $colors = Color::query()->whereIn('id', $this->pieceField($lines, 'color_id'))->pluck('name', 'id');
        $defects = DefectType::query()->whereIn('id', array_merge(...$this->pieceField($lines, 'defect_type_ids')))->get()->keyBy('id');

        $tier = $this->tier($input['turnaround_tier_id'] ?? null);
        $surchargePercent = $tier->surcharge_percent ?? 0;

        $errors = [];
        $engineLines = [];

        foreach ($lines as $index => $line) {
            $item = $items->get($line['item_id']);
            $service = $services->get($line['service_type_id']);
            $price = $prices->get("{$line['item_id']}:{$line['service_type_id']}");

            if ($item === null || ! $item->is_active) {
                $errors["lines.{$index}.item_id"] = __('orders.item_unavailable');

                continue;
            }

            if ($service === null || ! $service->is_active || $price === null) {
                $errors["lines.{$index}.service_type_id"] = __('orders.no_price', ['item' => $item->name, 'service' => $service->name ?? '']);

                continue;
            }

            $quantity = (float) $line['quantity'];

            if ($item->unit === PriceUnit::Piece && floor($quantity) !== $quantity) {
                $errors["lines.{$index}.quantity"] = __('orders.whole_quantity');

                continue;
            }

            $engineLines[$index] = [
                'unit_price' => (int) $price,
                'quantity_milli' => PriceCalculator::milli($quantity),
                'upcharges' => array_values(array_filter(array_map(
                    fn (int $id): ?array => ($upcharge = $upcharges->get($id)) === null ? null : ['type' => $upcharge->type, 'value' => $upcharge->value],
                    $line['upcharge_ids'] ?? [],
                ))),
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $discount = $this->discount($input['discount'] ?? null);
        $vat = $this->settings->get('tax.vat_percent');
        $vatBasisPoints = PriceCalculator::basisPoints(is_numeric($vat) ? $vat : 0);
        $includesVat = (bool) $this->settings->get('tax.prices_include_vat');
        $quote = $this->calculator->calculate(array_values($engineLines), $surchargePercent, $discount, $vatBasisPoints, $includesVat);

        // A promo code's discount depends on the subtotal, so it is worked
        // out after a first pass and applied as a fixed amount.
        $promo = null;
        $promoCode = $input['promo_code'] ?? null;

        if (is_string($promoCode) && trim($promoCode) !== '') {
            ['promo' => $promo, 'amount' => $promoAmount] = $this->promoCodes->apply($promoCode, $quote['subtotal'], $input['customer_id'] ?? null, $orderId);
            $discount = ['type' => DiscountType::Amount, 'value_bp' => $promoAmount];
            $quote = $this->calculator->calculate(array_values($engineLines), $surchargePercent, $discount, $vatBasisPoints, $includesVat);
        }

        $priced = [];

        foreach (array_keys($engineLines) as $position => $index) {
            $line = $lines[$index];
            /** @var Item $item */
            $item = $items[$line['item_id']];
            /** @var ServiceType $service */
            $service = $services[$line['service_type_id']];
            $lineQuote = $quote['lines'][$position];
            $applied = array_values(array_filter(array_map(fn (int $id): ?Upcharge => $upcharges->get($id), $line['upcharge_ids'] ?? [])));
            $quantity = (float) $line['quantity'];
            $pieceCount = $item->unit === PriceUnit::Piece ? (int) $quantity * $item->pieces_per_unit : 1;

            $priced[] = [
                'item_id' => $item->id,
                'service_type_id' => $service->id,
                'item_name' => $item->name,
                'service_name' => $service->name,
                'service_code' => $service->code,
                'unit' => $item->unit,
                'quantity' => number_format($quantity, 3, '.', ''),
                'unit_price' => $engineLines[$index]['unit_price'],
                'base_amount' => $lineQuote['base'],
                'upcharge_amount' => $lineQuote['upcharge'],
                'surcharge_amount' => $lineQuote['surcharge'],
                'line_total' => $lineQuote['total'],
                'notes' => $line['notes'] ?? null,
                'upcharges' => array_map(fn (Upcharge $upcharge, int $amount): array => [
                    'upcharge_id' => $upcharge->id,
                    'name' => $upcharge->name,
                    'type' => $upcharge->type->value,
                    'value' => $upcharge->value,
                    'amount' => $amount,
                ], $applied, $lineQuote['upcharges']),
                'piece_count' => $pieceCount,
                'pieces' => array_values(array_map(fn (array $piece): array => [
                    'color_id' => $piece['color_id'] ?? null,
                    'color_name' => isset($piece['color_id']) ? $colors->get($piece['color_id']) : null,
                    'brand' => $piece['brand'] ?? null,
                    'notes' => $piece['notes'] ?? null,
                    'defects' => array_values(array_filter(array_map(
                        fn (int $id): ?array => ($defect = $defects->get($id)) === null ? null : ['defect_type_id' => $id, 'name' => $defect->name, 'kind' => $defect->kind->value],
                        $piece['defect_type_ids'] ?? [],
                    ))),
                ], array_slice($line['pieces'] ?? [], 0, $pieceCount))),
            ];
        }

        return [
            'lines' => $priced,
            'tier' => $tier,
            'duration_hours' => $tier->duration_hours ?? self::FALLBACK_HOURS,
            'surcharge_percent' => $surchargePercent,
            'subtotal' => $quote['subtotal'],
            'discount_type' => $discount['type'] ?? null,
            'discount_value' => match (true) {
                $discount === null => '0',
                $promo !== null => (string) $quote['discount'],
                default => (string) ($input['discount']['value'] ?? '0'),
            },
            'discount_amount' => $quote['discount'],
            'promo' => $promo,
            'tax_amount' => $quote['tax'],
            'total' => $quote['total'],
        ];
    }

    /**
     * When an order taken at $receivedAt with this duration is due, counting
     * only the organization's working hours.
     */
    public function dueAt(CarbonInterface $receivedAt, int $hours): CarbonImmutable
    {
        $closed = (string) $this->settings->get('hours.closed_days');

        return $this->dueDates->dueAt(
            $receivedAt,
            $hours,
            (string) $this->settings->get('general.timezone'),
            (string) $this->settings->get('hours.opening'),
            (string) $this->settings->get('hours.closing'),
            $closed === '' ? [] : array_map('intval', explode(',', $closed)),
        );
    }

    private function tier(?int $id): ?TurnaroundTier
    {
        if ($id !== null) {
            $tier = TurnaroundTier::query()->active()->find($id);

            if ($tier === null) {
                throw ValidationException::withMessages(['turnaround_tier_id' => __('orders.tier_unavailable')]);
            }

            return $tier;
        }

        return TurnaroundTier::query()->active()->where('is_default', true)->first()
            ?? TurnaroundTier::query()->active()->ordered()->first();
    }

    /**
     * @param  array{type: string, value: int|float|string}|null  $discount
     * @return array{type: DiscountType, value_bp: int}|null
     */
    private function discount(?array $discount): ?array
    {
        if ($discount === null || (float) $discount['value'] <= 0) {
            return null;
        }

        $type = DiscountType::from($discount['type']);

        return [
            'type' => $type,
            'value_bp' => $type === DiscountType::Percent ? PriceCalculator::basisPoints($discount['value']) : (int) $discount['value'],
        ];
    }

    /**
     * Every value of one field across all pieces of all lines.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<mixed>
     */
    private function pieceField(array $lines, string $field): array
    {
        $values = [];

        foreach ($lines as $line) {
            foreach ($line['pieces'] ?? [] as $piece) {
                if (isset($piece[$field])) {
                    $values[] = $piece[$field];
                }
            }
        }

        return $values;
    }
}
