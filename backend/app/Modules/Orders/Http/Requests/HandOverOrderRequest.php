<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class HandOverOrderRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('orders.handover');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // The ready pieces the customer takes now; the others stay on the rack.
            'piece_ids' => ['required', 'array', 'min:1', 'max:500'],
            'piece_ids.*' => ['integer', 'distinct'],
        ];
    }
}
