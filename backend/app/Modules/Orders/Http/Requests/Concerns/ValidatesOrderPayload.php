<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Requests\Concerns;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\DiscountType;
use App\Support\Text\Digits;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Rules for the lines, turnaround tier and discount of an order, shared
 * by the price preview, creating an order and editing one.
 */
trait ValidatesOrderPayload
{
    abstract public function actor(): User;

    /**
     * @return array<string, list<mixed>>
     */
    protected function payloadRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.item_id' => ['required', 'integer', $this->inOrganization('items')],
            'lines.*.service_type_id' => ['required', 'integer', $this->inOrganization('service_types')],
            // Pieces for items counted one by one; kilograms or square metres otherwise.
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:10000'],
            'lines.*.upcharge_ids' => ['array', 'max:10'],
            'lines.*.upcharge_ids.*' => ['integer', 'distinct', $this->inOrganization('upcharges')],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
            // Description of each physical piece, in tag order; may cover only some.
            'lines.*.pieces' => ['array', 'max:500'],
            'lines.*.pieces.*.color_id' => ['nullable', 'integer', $this->inOrganization('colors')],
            'lines.*.pieces.*.brand' => ['nullable', 'string', 'max:50'],
            'lines.*.pieces.*.notes' => ['nullable', 'string', 'max:500'],
            'lines.*.pieces.*.defect_type_ids' => ['array', 'max:20'],
            'lines.*.pieces.*.defect_type_ids.*' => ['integer', 'distinct', $this->inOrganization('defect_types')],
            // The default tier is used when left out.
            'turnaround_tier_id' => ['nullable', 'integer'],
            'discount' => ['nullable', 'array'],
            'discount.type' => ['required_with:discount', Rule::enum(DiscountType::class)],
            // Rials for "amount", percent (up to two decimals) for "percent".
            'discount.value' => ['required_with:discount', 'numeric', 'min:0', $this->input('discount.type') === DiscountType::Percent->value ? 'max:100' : 'max:1000000000000'],
            'discount.reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function inOrganization(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('organization_id', $this->actor()->organization_id);
    }

    /**
     * Normalizes digits typed on a Persian keyboard in quantities and amounts.
     */
    protected function normalizeOrderDigits(): void
    {
        $lines = $this->input('lines');

        if (is_array($lines)) {
            foreach ($lines as $index => $line) {
                if (is_array($line) && is_string($line['quantity'] ?? null)) {
                    $lines[$index]['quantity'] = Digits::toLatinNumber($line['quantity']);
                }
            }
            $this->merge(['lines' => $lines]);
        }

        if (is_string($this->input('discount.value'))) {
            $this->merge(['discount' => [...(array) $this->input('discount'), 'value' => Digits::toLatinNumber((string) $this->input('discount.value'))]]);
        }
    }
}
