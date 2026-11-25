<?php

declare(strict_types=1);

use App\Modules\Logistics\Http\Controllers\SlotController;
use App\Modules\Logistics\Http\Controllers\SlotTemplateController;
use App\Modules\Logistics\Http\Controllers\ZoneController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('zones', ZoneController::class)->except('show');

    Route::get('slot-templates', [SlotTemplateController::class, 'index']);
    Route::post('slot-templates', [SlotTemplateController::class, 'store']);
    Route::put('slot-templates/{template}', [SlotTemplateController::class, 'update']);
    Route::delete('slot-templates/{template}', [SlotTemplateController::class, 'destroy']);

    Route::get('slots', [SlotController::class, 'index']);
    Route::put('slots/{slot}', [SlotController::class, 'update']);
});
