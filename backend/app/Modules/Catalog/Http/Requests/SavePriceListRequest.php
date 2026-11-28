<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class SavePriceListRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('catalog.manage');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            // New lists only: copy the default prices changed by this percent (−10 = 10% off).
            'percent' => ['nullable', 'numeric', 'min:-100', 'max:1000'],
        ];
    }
}
