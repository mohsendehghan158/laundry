<?php

declare(strict_types=1);

namespace App\Modules\B2B\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class SaveDepartmentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('companies.manage');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ];
    }
}
