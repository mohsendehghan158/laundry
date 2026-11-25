<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Requests;

use App\Modules\Production\Models\Rack;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SaveRackRequest extends ApiFormRequest
{
    protected array $latinDigits = ['code'];

    protected array $uppercase = ['code'];

    public function authorize(): bool
    {
        return $this->actor()->can('production.manage');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        /** @var Rack|null $rack */
        $rack = $this->route('rack');
        $branchId = $creating ? $this->integer('branch_id') : $rack?->branch_id;

        return [
            'branch_id' => $creating
                ? ['required', 'integer', Rule::exists('branches', 'id')->where('organization_id', $this->actor()->organization_id)->withoutTrashed()]
                : ['prohibited'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            // Slot codes are built from it: code "A" gives A-01, A-02…
            'code' => [
                $creating ? 'required' : 'prohibited', 'string', 'regex:/^[A-Z0-9]{1,5}$/',
                Rule::unique('racks', 'code')->where('branch_id', $branchId)->withoutTrashed(),
            ],
            'slot_count' => [$creating ? 'required' : 'sometimes', 'integer', 'min:1', 'max:200'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
