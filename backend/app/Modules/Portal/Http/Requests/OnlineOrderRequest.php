<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Requests;

use App\Modules\Customers\Models\Customer;
use App\Support\Http\Requests\ApiFormRequest;
use App\Support\Text\Digits;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * An online order as the customer puts it together: the pickup address
 * and window, and either the items or "the driver lists them". The quote
 * takes the same fields without the window.
 */
class OnlineOrderRequest extends ApiFormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $place = ! $this->routeIs('portal.orders.quote');

        return [
            /** One of the customer's addresses, inside a service zone. */
            'address_id' => ['required', 'integer'],
            /** A window from GET /customer/slots for that address. */
            'slot_id' => [$place ? 'required' : 'prohibited', 'integer'],
            /** Leave the items for the driver to list at the door. */
            'items_by_driver' => ['sometimes', 'boolean'],
            'lines' => ['required_unless:items_by_driver,true,1', 'prohibited_if:items_by_driver,true,1', 'array', 'max:50'],
            'lines.*.item_id' => ['required', 'integer', $this->inOrganization('items')],
            'lines.*.service_type_id' => ['required', 'integer', $this->inOrganization('service_types')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        if (is_array($lines)) {
            $this->merge(['lines' => array_map(
                fn (mixed $line): mixed => is_array($line) && isset($line['quantity']) && is_string($line['quantity'])
                    ? [...$line, 'quantity' => Digits::toLatin($line['quantity'])]
                    : $line,
                $lines,
            )]);
        }
    }

    private function inOrganization(string $table): Exists
    {
        /** @var Customer $customer */
        $customer = $this->user('customer');

        return Rule::exists($table, 'id')->where('organization_id', $customer->organization_id);
    }
}
