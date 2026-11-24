<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Http\Requests\Concerns\ValidatesUserAssignments;
use App\Modules\Identity\Models\User;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends ApiFormRequest
{
    use ValidatesUserAssignments;

    protected array $latinDigits = ['username', 'mobile'];

    protected array $lowercase = ['username', 'email'];

    public function authorize(): bool
    {
        return $this->actor()->can('update', $this->route('user'));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'username' => [
                'sometimes', 'required', 'string', 'regex:/^[a-z0-9._-]{3,30}$/',
                Rule::unique('users', 'username')->withoutTrashed()->ignore($user->id),
            ],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->withoutTrashed()->ignore($user->id),
            ],
            'mobile' => ['nullable', 'string', 'regex:/^09\d{9}$/'],
            // Leave out or send null to keep the current password.
            'password' => ['nullable', 'string', Password::min(8)],
            'roles' => ['sometimes', 'required', 'array', 'min:1'],
            ...$this->assignmentRules(),
        ];
    }
}
