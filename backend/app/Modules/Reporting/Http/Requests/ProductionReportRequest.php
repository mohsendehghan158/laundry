<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class ProductionReportRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('reports.production');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // Local dates (the organization's time zone), both included; at most a year.
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.$this->date('from')?->addYear()->format('Y-m-d')],
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
