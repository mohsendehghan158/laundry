<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class OpenShiftRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('shifts.operate');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('organization_id', $this->actor()->organization_id)->withoutTrashed()],
            // Cash already in the drawer, in rials.
            'opening_float' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
