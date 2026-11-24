<?php

declare(strict_types=1);

use App\Modules\Audit\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('activity-log', [ActivityLogController::class, 'index']);
});
