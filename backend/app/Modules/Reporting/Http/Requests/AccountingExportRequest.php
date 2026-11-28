<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Requests;

use Illuminate\Support\Arr;

/**
 * The sales report's period and branch, without a grouping.
 */
class AccountingExportRequest extends SalesReportRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), 'group');
    }
}
