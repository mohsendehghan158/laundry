<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Customers\Models\Customer;
use App\Support\Http\Requests\ApiFormRequest;
use App\Support\Text\Mobile;
use Illuminate\Validation\Rule;

/**
 * Creates (POST) or updates (PUT) a customer together with their
 * addresses. On update, the addresses sent replace the stored ones:
 * entries with an id are kept and changed, the rest are added, and
 * addresses left out are removed.
 */
class SaveCustomerRequest extends ApiFormRequest
{
    protected array $latinDigits = ['phone'];

    public function authorize(): bool
    {
        return $this->actor()->can('customers.manage');
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (is_string($this->input('mobile'))) {
            $this->merge(['mobile' => Mobile::normalize($this->input('mobile'))]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';
        $customer = $this->route('customer');
        $customerId = $customer instanceof Customer ? $customer->id : null;

        return [
            'name' => [$presence, 'string', 'max:100'],
            // Iranian mobile number; "+98…", "98…", spaces and Persian digits are accepted.
            'mobile' => [
                $presence, 'string', 'regex:/^09\d{9}$/',
                Rule::unique('customers', 'mobile')
                    ->where('organization_id', $this->actor()->organization_id)
                    ->withoutTrashed()
                    ->ignore($customerId),
            ],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s]{5,20}$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Shown as a warning whenever the customer is picked at the counter.
            'alert' => ['nullable', 'string', 'max:500'],
            'addresses' => ['array', 'max:10'],
            'addresses.*.id' => ['nullable', 'integer', Rule::exists('customer_addresses', 'id')->where('customer_id', $customerId ?? 0)],
            'addresses.*.title' => ['nullable', 'string', 'max:50'],
            'addresses.*.address' => ['required', 'string', 'max:500'],
            'addresses.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'addresses.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'addresses.*.is_default' => ['boolean'],
        ];
    }
}
