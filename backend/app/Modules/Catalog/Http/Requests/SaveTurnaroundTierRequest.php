<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

class SaveTurnaroundTierRequest extends CatalogRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->presence(), 'string', 'max:100'],
            // Working hours from intake until the order is due.
            'duration_hours' => [$this->presence(), 'integer', 'min:1', 'max:720'],
            // Added to line prices, e.g. 50 for +50%.
            'surcharge_percent' => ['integer', 'min:0', 'max:500'],
            // Making one tier the default clears the flag on the others.
            'is_default' => ['boolean'],
            ...$this->orderingRules(),
        ];
    }
}
