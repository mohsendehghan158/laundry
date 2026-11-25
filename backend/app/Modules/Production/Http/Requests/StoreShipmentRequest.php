<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreShipmentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('production.outsource');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organization = $this->actor()->organization_id;

        return [
            'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')->where('organization_id', $organization)->where('is_active', true)->withoutTrashed()],
            // The branch the pieces leave from (and come back to).
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('organization_id', $organization)->withoutTrashed()],
            'tags' => ['required', 'array', 'min:1', 'max:500'],
            'tags.*' => ['required', 'string', 'max:40'],
            'expected_at' => ['nullable', 'date'],
            // What the vendor charges, in rials.
            'cost' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
