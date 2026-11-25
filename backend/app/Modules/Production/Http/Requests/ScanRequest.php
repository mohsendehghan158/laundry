<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ScanRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('production.scan');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'station_id' => ['required', 'integer', Rule::exists('stations', 'id')->where('organization_id', $this->actor()->organization_id)->where('is_active', true)->withoutTrashed()],
            // One tag, or a batch (a bag or a cart scanned together).
            'tags' => ['required', 'array', 'min:1', 'max:200'],
            'tags.*' => ['required', 'string', 'max:40'],
            // Quality-control stations only: whether the pieces passed.
            'passed' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
            'defect_type_ids' => ['nullable', 'array', 'max:20'],
            'defect_type_ids.*' => ['integer', Rule::exists('defect_types', 'id')->where('organization_id', $this->actor()->organization_id)],
        ];
    }
}
