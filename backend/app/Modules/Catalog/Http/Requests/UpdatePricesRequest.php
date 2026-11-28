<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Validation\Rule;

class UpdatePricesRequest extends CatalogRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // A contract price list; the default list when left out.
            'price_list_id' => ['nullable', 'integer', Rule::exists('price_lists', 'id')->where('organization_id', $this->actor()->organization_id)],
            'prices' => ['required', 'array', 'min:1', 'max:2000'],
            'prices.*.item_id' => ['required', 'integer', $this->existsInOrganization('items')],
            'prices.*.service_type_id' => ['required', 'integer', $this->existsInOrganization('service_types')],
            // Whole rials per unit; null removes the price (the service is not offered for the item).
            'prices.*.price' => ['present', 'nullable', 'integer', 'min:0', 'max:1000000000000'],
        ];
    }
}
