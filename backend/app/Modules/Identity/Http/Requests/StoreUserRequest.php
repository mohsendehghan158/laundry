<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Http\Requests\Concerns\ValidatesUserAssignments;
use App\Modules\Identity\Models\User;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends ApiFormRequest
{
    use ValidatesUserAssignments;

    protected array $latinDigits = ['username', 'mobile'];

    protected array $lowercase = ['username', 'email'];

    public function authorize(): bool
    {
        return $this->actor()->can('create', User::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            // Lower-case letters, digits, dot, underscore and hyphen.
            'username' => [
                'required', 'string', 'regex:/^[a-z0-9._-]{3,30}$/',
                Rule::unique('users', 'username')->withoutTrashed(),
            ],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->withoutTrashed()],
            // Iranian mobile number, e.g. 09121234567.
            'mobile' => ['nullable', 'string', 'regex:/^09\d{9}$/'],
            'password' => ['required', 'string', Password::min(8)],
            // Role names, e.g. ["cashier"].
            'roles' => ['required', 'array', 'min:1'],
            ...$this->assignmentRules(),
        ];
    }
}
