<?php

declare(strict_types=1);

use App\Modules\Orders\Http\Controllers\OrderController;
use App\Modules\Orders\Http\Controllers\OrderPreviewController;
use App\Modules\Orders\Http\Controllers\OrderTransitionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('orders/price-preview', OrderPreviewController::class);
    Route::apiResource('orders', OrderController::class)->except('destroy');
    Route::post('orders/{order}/transitions', OrderTransitionController::class);
});
