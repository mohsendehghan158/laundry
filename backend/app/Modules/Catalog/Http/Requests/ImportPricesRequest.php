<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class ImportPricesRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('catalog.manage');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // The workbook downloaded from GET /prices/export, edited.
            'file' => ['required', 'file', 'extensions:xlsx', 'max:2048'],
            // false (default): only compare; true: also save, when the sheet has no errors.
            'apply' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['file' => (string) __('catalog.sheet.file')];
    }
}
