<?php

declare(strict_types=1);

use App\Modules\Orders\Http\Controllers\OrderController;
use App\Modules\Orders\Http\Controllers\OrderCounterController;
use App\Modules\Orders\Http\Controllers\OrderPreviewController;
use App\Modules\Orders\Http\Controllers\OrderTransitionController;
use App\Modules\Orders\Http\Controllers\PublicTrackingController;
use Illuminate\Support\Facades\Route;

// Public: the tracking link in SMS messages and on receipts.
Route::get('public/track/{publicId}', PublicTrackingController::class)
    ->where('publicId', '[0-9A-Za-z]{26}')
    ->middleware('throttle:public');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('orders/price-preview', OrderPreviewController::class);
    Route::get('orders/lookup', [OrderCounterController::class, 'lookup']);
    Route::get('receipt-settings', [OrderCounterController::class, 'receiptSettings']);
    Route::apiResource('orders', OrderController::class)->except('destroy');
    Route::post('orders/{order}/transitions', OrderTransitionController::class);
    Route::post('orders/{order}/handover', [OrderCounterController::class, 'handover']);
    Route::put('orders/{order}/rack', [OrderCounterController::class, 'rack']);
});
