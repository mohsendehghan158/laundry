<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\BuyerType;
use App\Support\Http\Requests\ApiFormRequest;
use App\Support\Text\NationalId;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Issues an invoice for orders of one customer, with the buyer's details
 * as they should be printed.
 */
class IssueInvoiceRequest extends ApiFormRequest
{
    protected array $latinDigits = ['buyer_national_id', 'buyer_economic_code', 'buyer_registration_number', 'buyer_postal_code', 'buyer_phone'];

    public function authorize(): bool
    {
        return $this->actor()->can('invoices.issue');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $legal = $this->input('buyer_type') === BuyerType::Legal->value;

        return [
            'order_ids' => ['required', 'array', 'min:1', 'max:200'],
            'order_ids.*' => ['integer', 'distinct'],
            'buyer_type' => ['required', Rule::enum(BuyerType::class)],
            'buyer_name' => ['required', 'string', 'max:150'],
            // National code (10 digits) of a person or national id (11 digits) of a company; required for companies.
            'buyer_national_id' => [$legal ? 'required' : 'nullable', 'string', function (string $attribute, mixed $value, Closure $fail) use ($legal): void {
                $valid = $legal ? NationalId::isValidLegalId((string) $value) : NationalId::isValidPersonCode((string) $value);
                if (! $valid) {
                    $fail(__($legal ? 'billing.invalid_legal_id' : 'billing.invalid_person_code'));
                }
            }],
            'buyer_economic_code' => ['nullable', 'string', 'regex:/^\d{10,14}$/'],
            'buyer_registration_number' => ['nullable', 'string', 'regex:/^\d{1,20}$/'],
            'buyer_postal_code' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'buyer_address' => ['nullable', 'string', 'max:500'],
            'buyer_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return list<int>
     */
    public function orderIds(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->validated('order_ids');

        return array_map(intval(...), $ids);
    }

    /**
     * @return array{type: string, name: string, national_id: string|null, economic_code: string|null, registration_number: string|null, postal_code: string|null, address: string|null, phone: string|null}
     */
    public function buyer(): array
    {
        $get = fn (string $key): ?string => ($value = $this->validated("buyer_{$key}")) === null || $value === '' ? null : (string) $value;

        return [
            'type' => (string) $this->validated('buyer_type'),
            'name' => (string) $get('name'),
            'national_id' => $get('national_id'),
            'economic_code' => $get('economic_code'),
            'registration_number' => $get('registration_number'),
            'postal_code' => $get('postal_code'),
            'address' => $get('address'),
            'phone' => $get('phone'),
        ];
    }
}
