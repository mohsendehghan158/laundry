<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Enums\UpchargeType;
use App\Modules\Catalog\Models\Upcharge;
use Illuminate\Validation\Rule;

class SaveUpchargeRequest extends CatalogRequest
{
    protected array $latinDigits = ['value'];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $type = $this->input('type');
        $current = $this->route('upcharge');

        if ($type === null && $current instanceof Upcharge) {
            $type = $current->type->value;
        }

        return [
            'name' => [$this->presence(), 'string', 'max:100'],
            'type' => [$this->presence(), Rule::enum(UpchargeType::class)],
            // Rials when fixed, whole percent (at most 1000) when percent.
            'value' => [
                $this->presence(), 'integer', 'min:0',
                $type === UpchargeType::Percent->value ? 'max:1000' : 'max:1000000000000',
            ],
            ...$this->orderingRules(),
        ];
    }
}
