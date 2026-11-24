<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Support\PermissionRegistry;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRoleRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('roles.update');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // The complete list of permissions the role should have.
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(PermissionRegistry::all())],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Granting a permission you do not hold yourself would be
                // a way to escalate your own access.
                $beyondOwn = array_diff($this->array('permissions'), $this->actor()->permissionNames());

                if ($beyondOwn !== []) {
                    $validator->errors()->add('permissions', __('api.errors.role_permission_beyond_own'));
                }
            },
        ];
    }
}
