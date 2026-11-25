<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('production.transfer');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $branch = Rule::exists('branches', 'id')->where('organization_id', $this->actor()->organization_id)->where('is_active', true)->withoutTrashed();

        return [
            'from_branch_id' => ['required', 'integer', $branch],
            'to_branch_id' => ['required', 'integer', 'different:from_branch_id', $branch],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
