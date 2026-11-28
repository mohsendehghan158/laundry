<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class ReceivablesRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('reports.view');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['branch_id' => ['nullable', 'integer']];
    }
}
