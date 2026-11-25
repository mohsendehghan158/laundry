<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Requests;

use App\Modules\Production\Enums\ClaimKind;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class OpenClaimRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('claims.manage');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'piece_id' => ['required', 'integer', Rule::exists('pieces', 'id')->where('organization_id', $this->actor()->organization_id)],
            'kind' => ['required', Rule::enum(ClaimKind::class)],
            'description' => ['required', 'string', 'max:1000'],
        ];
    }
}
