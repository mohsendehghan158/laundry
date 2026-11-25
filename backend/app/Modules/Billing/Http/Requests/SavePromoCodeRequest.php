<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Models\PromoCode;
use App\Modules\Billing\Support\PromoCodes;
use App\Modules\Orders\Enums\DiscountType;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Creates (POST) or updates (PUT) a promo code.
 */
class SavePromoCodeRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('promotions.manage');
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (is_string($this->input('code'))) {
            $this->merge(['code' => PromoCodes::normalize($this->input('code'))]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';
        $promo = $this->route('promo_code');
        $type = $this->input('type') ?? ($promo instanceof PromoCode ? $promo->type->value : null);

        return [
            'code' => [
                $presence, 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('promo_codes', 'code')
                    ->where('organization_id', $this->actor()->organization_id)
                    ->withoutTrashed()
                    ->ignore($promo instanceof PromoCode ? $promo->id : null),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => [$presence, Rule::enum(DiscountType::class)],
            // Rials for "amount"; percent (up to two decimals) for "percent".
            'value' => [$presence, 'numeric', 'gt:0', $type === DiscountType::Percent->value ? 'max:100' : 'max:1000000000000'],
            // Largest discount a percent code gives, in rials.
            'max_discount' => ['nullable', 'integer', 'min:0'],
            // Smallest order subtotal the code applies to, in rials.
            'min_order' => ['integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'first_order_only' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Validated values as stored: percent values in hundredths of a percent.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $data = $this->validated();
        $promo = $this->route('promo_code');
        $type = $data['type'] ?? ($promo instanceof PromoCode ? $promo->type->value : DiscountType::Amount->value);

        if (array_key_exists('value', $data)) {
            $data['value'] = $type === DiscountType::Percent->value ? (int) round((float) $data['value'] * 100) : (int) $data['value'];
        }

        return $data;
    }
}
