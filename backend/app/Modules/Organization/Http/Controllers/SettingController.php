<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Modules\Organization\Actions\UpdateSettings;
use App\Modules\Organization\Http\Requests\UpdateSettingsRequest;
use App\Modules\Organization\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SettingController
{
    /**
     * Show settings
     *
     * Every setting of the organization, with defaults for those never changed.
     *
     * @response array{data: array{general: array{currency_unit: 'toman'|'rial', timezone: string}, tax: array{vat_percent: float, prices_include_vat: bool}, receipt: array{header_text: string|null, footer_text: string|null}, hours: array{opening: string, closing: string, closed_days: string|null}, orders: array{max_discount_percent: float}}}
     */
    public function show(Settings $settings): JsonResponse
    {
        Gate::authorize('settings.view');

        return response()->json(['data' => $settings->nested()]);
    }

    /**
     * Update settings
     *
     * Send only the settings that change.
     *
     * @response array{data: array{general: array{currency_unit: 'toman'|'rial', timezone: string}, tax: array{vat_percent: float, prices_include_vat: bool}, receipt: array{header_text: string|null, footer_text: string|null}, hours: array{opening: string, closing: string, closed_days: string|null}, orders: array{max_discount_percent: float}}}
     */
    public function update(UpdateSettingsRequest $request, UpdateSettings $updateSettings, Settings $settings): JsonResponse
    {
        $updateSettings->handle($request->settings());

        return response()->json(['data' => $settings->nested()]);
    }
}
