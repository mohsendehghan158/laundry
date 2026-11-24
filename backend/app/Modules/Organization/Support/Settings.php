<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Models\Setting;
use Illuminate\Support\Arr;

/**
 * Reads and writes the settings of the current organization.
 */
final class Settings
{
    /**
     * Every setting with stored values laid over the defaults, keyed by
     * dotted key.
     *
     * @return array<string, mixed>
     */
    public function flat(): array
    {
        $stored = Setting::query()
            ->whereIn('key', array_keys(SettingsRegistry::definitions()))
            ->pluck('value', 'key')
            ->all();

        return [...SettingsRegistry::defaults(), ...$stored];
    }

    /**
     * The same data grouped the way the API exposes it,
     * e.g. ['tax' => ['vat_percent' => 10]].
     *
     * @return array<string, array<string, mixed>>
     */
    public function nested(): array
    {
        /** @var array<string, array<string, mixed>> */
        return Arr::undot($this->flat());
    }

    public function get(string $key): mixed
    {
        return $this->flat()[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $values  dotted key => value
     */
    public function put(array $values): void
    {
        foreach (Arr::only($values, array_keys(SettingsRegistry::definitions())) as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
