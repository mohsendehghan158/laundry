<?php

declare(strict_types=1);

namespace App\Modules\B2B\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;
use App\Support\Text\NationalId;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Creates (POST) or updates (PUT) a company and its contract.
 */
class SaveCompanyRequest extends ApiFormRequest
{
    protected array $latinDigits = ['national_id', 'economic_code', 'registration_number', 'postal_code', 'phone', 'contact_mobile'];

    public function authorize(): bool
    {
        return $this->actor()->can('companies.manage');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:150'],
            // 11-digit national id of the legal entity.
            'national_id' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! NationalId::isValidLegalId((string) $value)) {
                    $fail(__('billing.invalid_legal_id'));
                }
            }],
            'economic_code' => ['nullable', 'string', 'regex:/^\d{10,14}$/'],
            'registration_number' => ['nullable', 'string', 'regex:/^\d{1,20}$/'],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'contact_mobile' => ['nullable', 'string', 'regex:/^09\d{9}$/'],
            'contract_number' => ['nullable', 'string', 'max:50'],
            'contract_starts_on' => ['nullable', 'date_format:Y-m-d'],
            'contract_ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:contract_starts_on'],
            // A price list other than the default one.
            'price_list_id' => ['nullable', 'integer', Rule::exists('price_lists', 'id')->where('organization_id', $this->actor()->organization_id)->where('is_default', false)],
            // Rials; empty means the company pays like any customer (no account).
            'credit_limit' => ['nullable', 'integer', 'min:0', 'max:1000000000000000'],
            'payment_terms_days' => ['integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }
}
