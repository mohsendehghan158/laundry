<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Enums\DefectKind;
use Illuminate\Validation\Rule;

class SaveDefectTypeRequest extends CatalogRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->presence(), 'string', 'max:100'],
            'kind' => [$this->presence(), Rule::enum(DefectKind::class)],
            ...$this->orderingRules(),
        ];
    }
}
