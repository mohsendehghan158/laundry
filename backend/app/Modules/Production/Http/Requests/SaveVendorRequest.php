<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class SaveVendorRequest extends ApiFormRequest
{
    protected array $latinDigits = ['phone'];

    public function authorize(): bool
    {
        return $this->actor()->can('production.manage');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$presence, 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'services' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
