<?php

declare(strict_types=1);

use App\Modules\Billing\Http\Controllers\CashShiftController;
use App\Modules\Billing\Http\Controllers\OrderPaymentController;
use App\Modules\Billing\Http\Controllers\PromoCodeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('orders/{order}/payments', [OrderPaymentController::class, 'store']);
    Route::post('orders/{order}/refunds', [OrderPaymentController::class, 'refund']);

    Route::get('cash-shifts/current', [CashShiftController::class, 'current']);
    Route::get('cash-shifts', [CashShiftController::class, 'index']);
    Route::post('cash-shifts', [CashShiftController::class, 'store']);
    Route::get('cash-shifts/{cashShift}', [CashShiftController::class, 'show']);
    Route::post('cash-shifts/{cashShift}/close', [CashShiftController::class, 'close']);
    Route::post('cash-shifts/{cashShift}/movements', [CashShiftController::class, 'movement']);

    Route::apiResource('promo-codes', PromoCodeController::class)->except('show');
});
