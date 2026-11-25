<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class PhotoRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('production.scan') || $this->actor()->can('orders.update');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        // A phone photo, up to 8 MB.
        return ['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192']];
    }
}
