<?php

declare(strict_types=1);

use App\Modules\Production\Http\Controllers\AssemblyController;
use App\Modules\Production\Http\Controllers\BoardController;
use App\Modules\Production\Http\Controllers\ClaimController;
use App\Modules\Production\Http\Controllers\PhotoController;
use App\Modules\Production\Http\Controllers\PieceController;
use App\Modules\Production\Http\Controllers\RackController;
use App\Modules\Production\Http\Controllers\RealtimeController;
use App\Modules\Production\Http\Controllers\ScanController;
use App\Modules\Production\Http\Controllers\ShipmentController;
use App\Modules\Production\Http\Controllers\StationController;
use App\Modules\Production\Http\Controllers\TransferController;
use App\Modules\Production\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('stations', StationController::class)->except('show');
    Route::get('stations/{station}/activity', [StationController::class, 'activity']);

    Route::post('scans', ScanController::class);
    Route::get('production/board', [BoardController::class, 'show']);
    Route::get('realtime', RealtimeController::class);

    Route::apiResource('racks', RackController::class)->except('show');
    Route::post('assembly/scan', [AssemblyController::class, 'scan']);
    Route::get('assembly/{order}', [AssemblyController::class, 'show']);
    Route::post('assembly/{order}/complete', [AssemblyController::class, 'complete']);

    Route::get('transfer-destinations', [TransferController::class, 'destinations']);
    Route::apiResource('transfers', TransferController::class)->except('update');
    Route::post('transfers/{transfer}/pieces', [TransferController::class, 'addPieces']);
    Route::delete('transfers/{transfer}/pieces/{piece}', [TransferController::class, 'removePiece']);
    Route::post('transfers/{transfer}/send', [TransferController::class, 'send']);
    Route::post('transfers/{transfer}/receive', [TransferController::class, 'receive']);
    Route::post('transfers/{transfer}/close', [TransferController::class, 'close']);

    Route::apiResource('vendors', VendorController::class)->except('show');
    Route::apiResource('shipments', ShipmentController::class)->except('destroy')->parameters(['shipments' => 'shipment']);
    Route::post('shipments/{shipment}/receive', [ShipmentController::class, 'receive']);

    Route::get('claims', [ClaimController::class, 'index']);
    Route::post('claims', [ClaimController::class, 'store']);
    Route::post('claims/{claim}/resolve', [ClaimController::class, 'resolve']);
    Route::post('claims/{claim}/reject', [ClaimController::class, 'reject']);

    Route::post('pieces/{piece}/photos', [PhotoController::class, 'store'])->whereNumber('piece');
    Route::get('media/{media}', [PhotoController::class, 'show']);
    Route::delete('media/{media}', [PhotoController::class, 'destroy']);

    Route::get('pieces/{tag}', [PieceController::class, 'show'])->where('tag', '[^/]+');
    Route::post('pieces/{piece}/hold', [PieceController::class, 'hold'])->whereNumber('piece');
    Route::post('pieces/{piece}/release', [PieceController::class, 'release'])->whereNumber('piece');
});
