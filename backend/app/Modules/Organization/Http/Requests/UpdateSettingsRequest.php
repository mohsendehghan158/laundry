<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Support\SettingsRegistry;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Support\Arr;

class UpdateSettingsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('settings.update');
    }

    /**
     * Accepts any subset of the settings, grouped as in the response,
     * e.g. {"tax": {"vat_percent": 9}}.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return SettingsRegistry::rules();
    }

    /**
     * The submitted settings as dotted key => value.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $submitted = Arr::only(Arr::dot($this->validated()), array_keys(SettingsRegistry::definitions()));

        return Arr::map($submitted, fn (mixed $value, string $key): mixed => SettingsRegistry::cast($key, $value));
    }
}
