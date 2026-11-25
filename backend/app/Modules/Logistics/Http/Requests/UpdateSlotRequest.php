<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Http\Requests;

use App\Modules\Logistics\Models\Slot;
use App\Support\Http\Requests\ApiFormRequest;

class UpdateSlotRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('logistics.manage');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Slot $slot */
        $slot = $this->route('slot');

        return [
            // Never below the places already booked.
            'capacity' => ['sometimes', 'integer', 'min:'.max(1, $slot->booked), 'max:1000'],
            // A closed slot takes no new bookings; existing ones stay.
            'is_closed' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['capacity.min' => __('api.errors.slot_capacity_below_booked')];
    }
}
