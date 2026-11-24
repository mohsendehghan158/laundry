<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Http\Requests\Concerns\ValidatesOrderPayload;
use App\Support\Http\Requests\ApiFormRequest;

class UpdateOrderRequest extends ApiFormRequest
{
    use ValidatesOrderPayload;

    public function authorize(): bool
    {
        return $this->actor()->can('orders.update');
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->normalizeOrderDigits();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // The version the change was made on; a newer one on the server means someone else edited it.
            'version' => ['required', 'integer'],
            // Why the order is being changed after intake; kept in the audit log.
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...$this->payloadRules(),
        ];
    }
}
