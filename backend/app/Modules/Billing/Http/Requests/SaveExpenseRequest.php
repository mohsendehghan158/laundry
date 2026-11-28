<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\PaymentMethod;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Records (POST) or changes (PUT) an expense.
 */
class SaveExpenseRequest extends ApiFormRequest
{
    protected array $latinDigits = ['reference'];

    public function authorize(): bool
    {
        return $this->actor()->can('expenses.manage');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';
        $organization = $this->actor()->organization_id;

        return [
            'branch_id' => [$presence, 'integer', Rule::exists('branches', 'id')->where('organization_id', $organization)->withoutTrashed()],
            'expense_category_id' => [$presence, 'integer', Rule::exists('expense_categories', 'id')->where('organization_id', $organization)->withoutTrashed()],
            // The local day the money was spent.
            'spent_on' => [$presence, 'date_format:Y-m-d'],
            // Whole rials.
            'amount' => [$presence, 'integer', 'min:1', 'max:1000000000000'],
            'method' => [$presence, Rule::enum(PaymentMethod::class)],
            'description' => [$presence, 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            // Cash taken from the branch's open register (new expenses only).
            'from_register' => ['boolean'],
        ];
    }
}
