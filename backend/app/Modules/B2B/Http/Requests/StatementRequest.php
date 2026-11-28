<?php

declare(strict_types=1);

namespace App\Modules\B2B\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class StatementRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can($this->isMethod('POST') ? 'invoices.issue' : 'companies.view');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Local dates (the organization's time zone), both included; at most a year.
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.$this->date('from')?->addYear()->format('Y-m-d')],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
