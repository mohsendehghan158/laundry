<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class UpdateOrganizationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('organization.update');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }
}
