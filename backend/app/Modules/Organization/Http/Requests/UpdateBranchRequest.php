<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Enums\BranchType;
use App\Modules\Organization\Models\Branch;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends ApiFormRequest
{
    protected array $latinDigits = ['code', 'phone'];

    protected array $uppercase = ['code'];

    public function authorize(): bool
    {
        return $this->actor()->can('update', $this->route('branch'));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Branch $branch */
        $branch = $this->route('branch');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'code' => [
                'sometimes', 'required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/',
                Rule::unique('branches', 'code')
                    ->where('organization_id', $branch->organization_id)
                    ->withoutTrashed()
                    ->ignore($branch->id),
            ],
            'type' => ['sometimes', 'required', Rule::enum(BranchType::class)],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s]{5,20}$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }
}
