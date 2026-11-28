<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class VoidInvoiceRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('invoices.void');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
