<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

class SaveColorRequest extends CatalogRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->presence(), 'string', 'max:50'],
            'hex' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ...$this->orderingRules(),
        ];
    }
}
