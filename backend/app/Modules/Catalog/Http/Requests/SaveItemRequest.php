<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Enums\PriceUnit;
use Illuminate\Validation\Rule;

class SaveItemRequest extends CatalogRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'item_category_id' => [$this->presence(), 'integer', $this->existsInOrganization('item_categories')],
            'name' => [$this->presence(), 'string', 'max:100'],
            'unit' => [$this->presence(), Rule::enum(PriceUnit::class)],
            // Physical pieces (and tags) one unit makes, e.g. 2 for a suit.
            'pieces_per_unit' => ['integer', 'min:1', 'max:50'],
            'icon' => ['nullable', 'string', 'max:50'],
            ...$this->orderingRules(),
        ];
    }
}
