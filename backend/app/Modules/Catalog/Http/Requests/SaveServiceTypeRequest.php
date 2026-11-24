<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

class SaveServiceTypeRequest extends CatalogRequest
{
    protected array $latinDigits = ['code'];

    protected array $uppercase = ['code'];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->presence(), 'string', 'max:100'],
            // Short code printed on tags and receipts, e.g. "DC".
            'code' => [
                $this->presence(), 'string', 'max:10', 'regex:/^[A-Z0-9]+$/',
                $this->uniqueInOrganization('service_types', 'code', $this->routeId('service_type')),
            ],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ...$this->orderingRules(),
        ];
    }
}
