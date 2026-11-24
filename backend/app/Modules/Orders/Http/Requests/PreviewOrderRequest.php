<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Http\Requests\Concerns\ValidatesOrderPayload;
use App\Support\Http\Requests\ApiFormRequest;

class PreviewOrderRequest extends ApiFormRequest
{
    use ValidatesOrderPayload;

    public function authorize(): bool
    {
        return $this->actor()->can('orders.create') || $this->actor()->can('orders.update');
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
        return [...$this->payloadRules()];
    }
}
