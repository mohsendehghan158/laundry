<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Requests;

use App\Modules\Production\Enums\StationKind;
use App\Modules\Production\Models\Station;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SaveStationRequest extends ApiFormRequest
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
        /** @var Station|null $station */
        $station = $this->route('station');
        $branchId = $creating ? $this->integer('branch_id') : $station?->branch_id;

        return [
            // A station stays in the branch it was made for.
            'branch_id' => $creating
                ? ['required', 'integer', Rule::exists('branches', 'id')->where('organization_id', $this->actor()->organization_id)->withoutTrashed()]
                : ['prohibited'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            'code' => [
                $creating ? 'required' : 'sometimes', 'string', 'regex:/^[A-Z0-9-]{1,10}$/',
                Rule::unique('stations', 'code')->where('branch_id', $branchId)->withoutTrashed()->ignore($station?->id),
            ],
            'kind' => ['sometimes', Rule::enum(StationKind::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
