<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Enums\OrderStatus;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class TransitionOrderRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->input('to') === OrderStatus::Cancelled->value
            ? $this->actor()->can('orders.cancel')
            : $this->actor()->can('orders.update');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', Rule::enum(OrderStatus::class)->only([OrderStatus::InProcess, OrderStatus::Ready, OrderStatus::Cancelled])],
            // Required when cancelling.
            'reason' => [Rule::requiredIf($this->input('to') === OrderStatus::Cancelled->value), 'nullable', 'string', 'max:500'],
            // Where the finished order is kept, e.g. "A-12"; used when marking it ready.
            'rack_location' => ['nullable', 'string', 'max:50'],
        ];
    }
}
