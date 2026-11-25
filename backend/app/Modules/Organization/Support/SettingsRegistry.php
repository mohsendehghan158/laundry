<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use Illuminate\Validation\Rule;

/**
 * The single list of organization settings: every key the API accepts,
 * its default and how a new value is validated.
 */
final class SettingsRegistry
{
    /**
     * @return array<string, array{default: mixed, type: 'string'|'number'|'boolean', rules: list<mixed>}>
     */
    public static function definitions(): array
    {
        return [
            // Amounts are stored in rials; this only changes how they are shown.
            'general.currency_unit' => [
                'default' => 'toman',
                'type' => 'string',
                'rules' => ['string', Rule::in(['toman', 'rial'])],
            ],
            'general.timezone' => [
                'default' => 'Asia/Tehran',
                'type' => 'string',
                'rules' => ['string', 'timezone:all'],
            ],
            'tax.vat_percent' => [
                'default' => 10,
                'type' => 'number',
                'rules' => ['numeric', 'min:0', 'max:100'],
            ],
            'tax.prices_include_vat' => [
                'default' => false,
                'type' => 'boolean',
                'rules' => ['boolean'],
            ],
            'receipt.header_text' => [
                'default' => null,
                'type' => 'string',
                'rules' => ['nullable', 'string', 'max:500'],
            ],
            'receipt.footer_text' => [
                'default' => null,
                'type' => 'string',
                'rules' => ['nullable', 'string', 'max:500'],
            ],
            // Working hours: due dates count only time inside them.
            'hours.opening' => [
                'default' => '09:00',
                'type' => 'string',
                'rules' => ['string', 'date_format:H:i'],
            ],
            'hours.closing' => [
                'default' => '21:00',
                'type' => 'string',
                'rules' => ['string', 'date_format:H:i'],
            ],
            // Weekdays the business is closed, comma separated: 0 = Sunday … 6 = Saturday (5 = Friday).
            'hours.closed_days' => [
                'default' => '5',
                'type' => 'string',
                'rules' => ['nullable', 'string', 'regex:/^([0-6](,[0-6]){0,6})?$/'],
            ],
            // Every piece must be scanned at the assembly screen before an order can be marked ready (no ticking pieces off by hand).
            'production.assembly_scan_required' => [
                'default' => false,
                'type' => 'boolean',
                'rules' => ['boolean'],
            ],
            // Largest manual discount, in percent of the order, for staff without unlimited discount rights.
            'orders.max_discount_percent' => [
                'default' => 10,
                'type' => 'number',
                'rules' => ['numeric', 'min:0', 'max:100'],
            ],
            // Pickup and delivery: how many days ahead customers can book (today included),
            // and how soon a window may start to still be offered.
            'logistics.booking_days' => [
                'default' => 7,
                'type' => 'number',
                'rules' => ['integer', 'min:1', 'max:30'],
            ],
            'logistics.lead_minutes' => [
                'default' => 120,
                'type' => 'number',
                'rules' => ['integer', 'min:0', 'max:2880'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return array_map(fn (array $definition): mixed => $definition['default'], self::definitions());
    }

    /**
     * Brings a validated value to its stored type, so "10" and 10 are
     * saved (and compared) as the same thing.
     */
    public static function cast(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match (self::definitions()[$key]['type']) {
            'number' => is_numeric($value) ? $value + 0 : $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => $value,
        };
    }

    /**
     * Validation rules for a partial update, keyed by dotted setting key.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return array_map(
            fn (array $definition): array => ['sometimes', ...$definition['rules']],
            self::definitions(),
        );
    }
}
