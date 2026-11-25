<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class UpdateTemplateRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('notifications.manage');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Placeholders such as {ticket} are filled in when sending.
            'body' => ['required', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
