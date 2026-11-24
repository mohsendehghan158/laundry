<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

class SaveItemCategoryRequest extends CatalogRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->presence(), 'string', 'max:100'],
            // Icon name for the counter's touch grid.
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ...$this->orderingRules(),
        ];
    }
}
