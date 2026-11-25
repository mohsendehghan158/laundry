<?php

declare(strict_types=1);

use App\Modules\Portal\Http\Controllers\CustomerAddressController;
use App\Modules\Portal\Http\Controllers\CustomerAuthController;
use App\Modules\Portal\Http\Controllers\CustomerOrderController;
use Illuminate\Support\Facades\Route;

// The customer portal. Public routes run in the portal's organization.
Route::middleware('portal')->group(function (): void {
    Route::get('portal/settings', [CustomerAuthController::class, 'settings'])->middleware('throttle:public');
    Route::post('customer/auth/otp', [CustomerAuthController::class, 'otp'])->middleware('throttle:otp');
    Route::post('customer/auth/verify', [CustomerAuthController::class, 'verify'])->middleware('throttle:otp')->name('customer.auth.verify');
});

Route::middleware(['auth:customer', 'customer.context'])->prefix('customer')->group(function (): void {
    Route::get('me', [CustomerAuthController::class, 'me']);
    Route::put('me', [CustomerAuthController::class, 'update']);
    Route::post('auth/logout', [CustomerAuthController::class, 'logout']);

    Route::get('addresses', [CustomerAddressController::class, 'index']);
    Route::post('addresses', [CustomerAddressController::class, 'store']);
    Route::put('addresses/{address}', [CustomerAddressController::class, 'update'])->whereNumber('address');
    Route::delete('addresses/{address}', [CustomerAddressController::class, 'destroy'])->whereNumber('address');

    Route::get('orders', [CustomerOrderController::class, 'index']);
    Route::get('orders/{publicId}', [CustomerOrderController::class, 'show'])->where('publicId', '[0-9A-Za-z]{26}');
});
