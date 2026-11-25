<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Http\Requests;

use App\Modules\Logistics\Support\Polygon;
use App\Support\Http\Requests\ApiFormRequest;
use Closure;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class SaveZoneRequest extends ApiFormRequest
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
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            // The branch whose drivers serve the zone.
            'branch_id' => [$presence, 'integer', Rule::exists('branches', 'id')->where('organization_id', $this->actor()->organization_id)->withoutTrashed()],
            'name' => [$presence, 'string', 'max:100'],
            // Corners as [[latitude, longitude], …], 3 to 200 of them.
            'polygon' => [$presence, 'array', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    Polygon::from(is_array($value) ? $value : []);
                } catch (InvalidArgumentException) {
                    $fail(__('api.errors.zone_polygon_invalid'));
                }
            }],
            // Rials.
            'delivery_fee' => ['sometimes', 'integer', 'min:0', 'max:100000000000'],
            // Orders from this total (rials) get free delivery; null = never.
            'free_delivery_over' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000000'],
            // Smallest online order (rials); null = no minimum.
            'min_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function polygon(): ?Polygon
    {
        $points = $this->validated('polygon');

        return is_array($points) ? Polygon::from($points) : null;
    }
}
