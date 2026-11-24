<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

class UpdatePricesRequest extends CatalogRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'prices' => ['required', 'array', 'min:1', 'max:2000'],
            'prices.*.item_id' => ['required', 'integer', $this->existsInOrganization('items')],
            'prices.*.service_type_id' => ['required', 'integer', $this->existsInOrganization('service_types')],
            // Whole rials per unit; null removes the price (the service is not offered for the item).
            'prices.*.price' => ['present', 'nullable', 'integer', 'min:0', 'max:1000000000000'],
        ];
    }
}
