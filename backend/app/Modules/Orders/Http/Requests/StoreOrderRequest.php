<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Http\Requests\Concerns\ValidatesOrderPayload;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends ApiFormRequest
{
    use ValidatesOrderPayload;

    public function authorize(): bool
    {
        return $this->actor()->can('orders.create');
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
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('organization_id', $this->actor()->organization_id)->withoutTrashed()],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('organization_id', $this->actor()->organization_id)->withoutTrashed()],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...$this->payloadRules(),
        ];
    }

    /**
     * The optional Idempotency-Key header: retrying a create with the same
     * key returns the order made the first time instead of a duplicate.
     */
    public function idempotencyKey(): ?string
    {
        $key = $this->header('Idempotency-Key');

        return is_string($key) && $key !== '' ? mb_substr($key, 0, 100) : null;
    }
}
