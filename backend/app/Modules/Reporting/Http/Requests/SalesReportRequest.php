<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Requests;

use App\Modules\Reporting\Support\Reports;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SalesReportRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('reports.view');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Local dates (the organization's time zone), both included.
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.$this->date('from')?->addYear()->format('Y-m-d')],
            'group' => ['required', Rule::in(Reports::GROUPS)],
            'branch_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['from' => (string) __('reports.from'), 'to' => (string) __('reports.to')];
    }
}
