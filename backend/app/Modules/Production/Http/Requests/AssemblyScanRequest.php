<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class AssemblyScanRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('production.scan') || $this->actor()->can('orders.update');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['tag' => ['required', 'string', 'max:40']];
    }
}
