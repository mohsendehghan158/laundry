<?php

declare(strict_types=1);

use App\Modules\Production\Http\Controllers\AssemblyController;
use App\Modules\Production\Http\Controllers\PieceController;
use App\Modules\Production\Http\Controllers\RackController;
use App\Modules\Production\Http\Controllers\ScanController;
use App\Modules\Production\Http\Controllers\StationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('stations', StationController::class)->except('show');
    Route::get('stations/{station}/activity', [StationController::class, 'activity']);

    Route::post('scans', ScanController::class);

    Route::apiResource('racks', RackController::class)->except('show');
    Route::post('assembly/scan', [AssemblyController::class, 'scan']);
    Route::get('assembly/{order}', [AssemblyController::class, 'show']);
    Route::post('assembly/{order}/complete', [AssemblyController::class, 'complete']);

    Route::get('pieces/{tag}', [PieceController::class, 'show'])->where('tag', '[^/]+');
    Route::post('pieces/{piece}/hold', [PieceController::class, 'hold'])->whereNumber('piece');
    Route::post('pieces/{piece}/release', [PieceController::class, 'release'])->whereNumber('piece');
});
