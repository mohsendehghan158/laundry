<?php

declare(strict_types=1);

namespace App\Modules\B2B\Http\Requests;

use App\Modules\B2B\Enums\DeliveryNoteType;
use App\Modules\B2B\Models\DeliveryNote;
use App\Support\Http\Requests\ApiFormRequest;
use App\Support\Text\Digits;
use Illuminate\Validation\Rule;

/**
 * Records (POST) or changes (PUT) a draft delivery note.
 */
class SaveDeliveryNoteRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('companies.delivery');
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $lines = $this->input('lines');
        if (is_array($lines)) {
            $this->merge(['lines' => array_map(function (mixed $line): mixed {
                if (! is_array($line)) {
                    return $line;
                }
                foreach (['expected_quantity', 'counted_quantity'] as $key) {
                    if (is_string($line[$key] ?? null)) {
                        $line[$key] = Digits::toLatinNumber($line[$key]);
                    }
                }

                return $line;
            }, $lines)]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $id = $this->route('deliveryNote');
        $note = is_numeric($id) ? DeliveryNote::query()->find((int) $id) : null;
        $type = $note instanceof DeliveryNote ? $note->type->value : $this->input('type');
        $in = $type === DeliveryNoteType::In->value;
        $creating = $this->isMethod('POST');
        $organization = $this->actor()->organization_id;

        return [
            'type' => [$creating ? 'required' : 'prohibited', Rule::enum(DeliveryNoteType::class)],
            'company_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('companies', 'id')->where('organization_id', $organization)->whereNull('deleted_at')],
            // In: the branch receiving the items and the company's customer the order is for.
            'branch_id' => [$in ? ($creating ? 'required' : 'sometimes') : 'prohibited', 'integer'],
            'customer_id' => [$in ? ($creating ? 'required' : 'sometimes') : 'prohibited', 'integer'],
            'company_department_id' => [$in ? 'nullable' : 'prohibited', 'integer'],
            // Out: the order going back.
            'order_id' => [$in ? 'prohibited' : ($creating ? 'required' : 'sometimes'), 'integer'],
            'lines' => [$in ? ($creating ? 'required' : 'sometimes') : 'nullable', 'array', 'max:200'],
            'lines.*.item_id' => [$in ? 'required' : 'prohibited', 'integer', Rule::exists('items', 'id')->where('organization_id', $organization)],
            'lines.*.service_type_id' => [$in ? 'required' : 'prohibited', 'integer', Rule::exists('service_types', 'id')->where('organization_id', $organization)],
            'lines.*.order_line_id' => [$in ? 'prohibited' : 'required', 'integer'],
            // In: what the company stated. Out: not sent (the order's quantities).
            'lines.*.expected_quantity' => [$in ? 'required' : 'prohibited', 'numeric', 'min:0', 'max:100000'],
            // In: what was counted here. Out: what is being sent.
            'lines.*.counted_quantity' => ['required', 'numeric', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
