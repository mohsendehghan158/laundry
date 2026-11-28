<?php

declare(strict_types=1);

namespace App\Modules\B2B\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

class ConfirmDeliveryNoteRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('companies.delivery');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Who counted and signed for the company.
            'counterpart_name' => ['required', 'string', 'max:100'],
            // Outgoing notes: what the company counted, per line id; lines left out keep what was sent.
            'received' => ['array'],
            'received.*.line_id' => ['required', 'integer'],
            'received.*.quantity' => ['required', 'numeric', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array<int, int|float|string>
     */
    public function received(): array
    {
        /** @var list<array{line_id: int, quantity: int|float|string}> $rows */
        $rows = $this->validated('received') ?? [];

        return collect($rows)->mapWithKeys(fn (array $row): array => [(int) $row['line_id'] => $row['quantity']])->all();
    }
}
