<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends ApiFormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }
}
