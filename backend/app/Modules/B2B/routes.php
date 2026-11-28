<?php

declare(strict_types=1);

use App\Modules\B2B\Http\Controllers\CompanyController;
use App\Modules\B2B\Http\Controllers\DeliveryNoteController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('companies', [CompanyController::class, 'index']);
    Route::post('companies', [CompanyController::class, 'store']);
    Route::get('companies/{company}', [CompanyController::class, 'show'])->whereNumber('company');
    Route::put('companies/{company}', [CompanyController::class, 'update']);
    Route::delete('companies/{company}', [CompanyController::class, 'destroy']);

    Route::post('companies/{company}/departments', [CompanyController::class, 'storeDepartment']);
    Route::put('companies/{company}/departments/{department}', [CompanyController::class, 'updateDepartment']);
    Route::delete('companies/{company}/departments/{department}', [CompanyController::class, 'destroyDepartment']);

    Route::post('companies/{company}/members', [CompanyController::class, 'addMember']);
    Route::delete('companies/{company}/members/{customer}', [CompanyController::class, 'removeMember']);

    Route::get('delivery-notes', [DeliveryNoteController::class, 'index']);
    Route::post('delivery-notes', [DeliveryNoteController::class, 'store']);
    Route::get('delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'show'])->whereNumber('deliveryNote');
    Route::put('delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'update'])->whereNumber('deliveryNote');
    Route::post('delivery-notes/{deliveryNote}/confirm', [DeliveryNoteController::class, 'confirm'])->whereNumber('deliveryNote');
    Route::delete('delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'destroy'])->whereNumber('deliveryNote');
});
