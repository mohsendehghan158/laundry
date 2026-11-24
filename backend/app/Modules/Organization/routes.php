<?php

declare(strict_types=1);

use App\Modules\Organization\Http\Controllers\BranchController;
use App\Modules\Organization\Http\Controllers\OrganizationController;
use App\Modules\Organization\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('organization', [OrganizationController::class, 'show']);
    Route::put('organization', [OrganizationController::class, 'update']);

    Route::get('settings', [SettingController::class, 'show']);
    Route::put('settings', [SettingController::class, 'update']);

    Route::apiResource('branches', BranchController::class);
});
