<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class CompleteAssemblyRequest extends ApiFormRequest
{
    protected array $latinDigits = ['rack_location'];

    protected array $uppercase = ['rack_location'];

    public function authorize(): bool
    {
        return $this->actor()->can('production.scan') || $this->actor()->can('orders.update');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Pieces ticked off by hand at the counter (not allowed when scanning is required).
            'piece_ids' => ['nullable', 'array', 'max:500'],
            'piece_ids.*' => ['integer'],
            // A slot of the order's branch's racks, or a free-text place when the branch has none.
            'rack_slot_id' => ['nullable', 'integer', Rule::exists('rack_slots', 'id')->where('organization_id', $this->actor()->organization_id)],
            'rack_location' => ['nullable', 'string', 'max:50'],
            // Rack what has been gathered while other pieces are still in work.
            'partial' => ['boolean'],
        ];
    }
}
