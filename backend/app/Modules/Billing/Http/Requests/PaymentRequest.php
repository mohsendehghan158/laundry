<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\PaymentMethod;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends ApiFormRequest
{
    protected array $latinDigits = ['reference'];

    public function authorize(): bool
    {
        return $this->actor()->can('payments.create');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            // Whole rials; at most the order's balance.
            'amount' => ['required', 'integer', 'min:1'],
            // Card trace number or transfer reference.
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
