<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class HoldPieceRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('production.scan');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }
}
