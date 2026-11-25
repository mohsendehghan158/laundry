<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;
use App\Support\Text\Digits;
use App\Support\Text\Mobile;

class OtpRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge(['mobile' => Mobile::normalize((string) $this->input('mobile', '')), 'code' => trim((string) preg_replace('/\D/', '', Digits::toLatin((string) $this->input('code', ''))))]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'mobile' => ['required', 'regex:/^09\d{9}$/'],
            // Only when verifying.
            'code' => [$this->routeIs('customer.auth.verify') ? 'required' : 'nullable', 'digits:5'],
        ];
    }
}
