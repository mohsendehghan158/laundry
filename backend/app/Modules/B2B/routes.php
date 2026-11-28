<?php

declare(strict_types=1);

use App\Modules\B2B\Http\Controllers\CompanyController;
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
});
