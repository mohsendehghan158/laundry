<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\CashMovementType;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class CashMovementRequest extends ApiFormRequest
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
            'type' => ['required', Rule::enum(CashMovementType::class)],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
