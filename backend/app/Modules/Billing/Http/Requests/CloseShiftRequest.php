<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class CloseShiftRequest extends ApiFormRequest
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
            // Cash counted in the drawer at closing, in rials.
            'counted_cash' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
