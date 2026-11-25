<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class AddressRequest extends ApiFormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => ['nullable', 'string', 'max:50'],
            'address' => [$presence, 'string', 'max:500'],
            // The point on the map, for pickups and deliveries.
            'latitude' => [$presence, 'numeric', 'between:-90,90'],
            'longitude' => [$presence, 'numeric', 'between:-180,180'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
