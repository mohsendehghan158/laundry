<?php

declare(strict_types=1);

use App\Modules\Notifications\Http\Controllers\NotificationLogController;
use App\Modules\Notifications\Http\Controllers\NotificationTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('notification-templates', [NotificationTemplateController::class, 'index']);
    Route::put('notification-templates/{event}', [NotificationTemplateController::class, 'update']);
    Route::get('notification-logs', [NotificationLogController::class, 'index']);
    Route::post('notification-logs/{notificationLog}/retry', [NotificationLogController::class, 'retry']);
});
