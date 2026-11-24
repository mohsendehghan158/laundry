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
