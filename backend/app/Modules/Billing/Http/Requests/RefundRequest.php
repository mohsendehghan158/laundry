<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\PaymentMethod;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class RefundRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('payments.refund');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            // Whole rials; at most what has been paid.
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
