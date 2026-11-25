<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class UpdateRackRequest extends ApiFormRequest
{
    protected array $latinDigits = ['rack_location'];

    protected array $uppercase = ['rack_location'];

    public function authorize(): bool
    {
        return $this->actor()->can('orders.update');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Where the order is kept, e.g. "A-12"; null clears it.
            'rack_location' => ['present', 'nullable', 'string', 'max:50'],
        ];
    }
}
