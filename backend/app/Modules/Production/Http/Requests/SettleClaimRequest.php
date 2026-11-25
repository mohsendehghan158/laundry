<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class SettleClaimRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('claims.manage');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', 'max:1000'],
            // Paid to the customer, in rials (when resolving).
            'compensation' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
        ];
    }
}
