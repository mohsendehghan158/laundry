<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Models\ExpenseCategory;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SaveExpenseCategoryRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('expenses.categories');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $category = $this->route('expense_category');

        return [
            'name' => [
                $this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:100',
                Rule::unique('expense_categories', 'name')
                    ->where('organization_id', $this->actor()->organization_id)
                    ->withoutTrashed()
                    ->ignore($category instanceof ExpenseCategory ? $category->id : null),
            ],
            'is_active' => ['boolean'],
        ];
    }
}
