<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Http\Requests;

use App\Modules\Logistics\Models\SlotTemplate;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveSlotTemplateRequest extends ApiFormRequest
{
    protected array $latinDigits = ['starts_at', 'ends_at'];

    public function authorize(): bool
    {
        return $this->actor()->can('logistics.manage');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $presence = $creating ? 'required' : 'sometimes';

        return [
            // A template stays with the branch it was made for.
            'branch_id' => $creating
                ? ['required', 'integer', Rule::exists('branches', 'id')->where('organization_id', $this->actor()->organization_id)->withoutTrashed()]
                : ['prohibited'],
            // 0 = Sunday … 6 = Saturday.
            'weekday' => [$presence, 'integer', 'between:0,6'],
            // Local times, "09:00".
            'starts_at' => [$presence, 'date_format:H:i'],
            'ends_at' => [$presence, 'date_format:H:i'],
            // Pickups and deliveries the window takes.
            'capacity' => [$presence, 'integer', 'min:1', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var SlotTemplate|null $template */
                $template = $this->route('template');
                $branchId = $template->branch_id ?? $this->integer('branch_id');
                $weekday = $this->has('weekday') ? $this->integer('weekday') : $template?->weekday;
                $starts = (string) ($this->input('starts_at') ?? substr((string) $template?->starts_at, 0, 5));
                $ends = (string) ($this->input('ends_at') ?? substr((string) $template?->ends_at, 0, 5));

                if ($ends <= $starts) {
                    $validator->errors()->add('ends_at', __('api.errors.slot_ends_before_start'));

                    return;
                }

                // Two windows of one branch on one day may not overlap.
                $overlaps = SlotTemplate::query()
                    ->where('branch_id', $branchId)
                    ->where('weekday', $weekday)
                    ->whereKeyNot($template->id ?? 0)
                    ->where('starts_at', '<', $ends)
                    ->where('ends_at', '>', $starts)
                    ->exists();

                if ($overlaps) {
                    $validator->errors()->add('starts_at', __('api.errors.slot_overlap'));
                }
            },
        ];
    }
}
