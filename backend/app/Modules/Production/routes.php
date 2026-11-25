<?php

declare(strict_types=1);

use App\Modules\Production\Http\Controllers\PieceController;
use App\Modules\Production\Http\Controllers\ScanController;
use App\Modules\Production\Http\Controllers\StationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('stations', StationController::class)->except('show');
    Route::get('stations/{station}/activity', [StationController::class, 'activity']);

    Route::post('scans', ScanController::class);

    Route::get('pieces/{tag}', [PieceController::class, 'show'])->where('tag', '[^/]+');
    Route::post('pieces/{piece}/hold', [PieceController::class, 'hold'])->whereNumber('piece');
    Route::post('pieces/{piece}/release', [PieceController::class, 'release'])->whereNumber('piece');
});
