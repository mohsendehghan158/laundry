<?php

declare(strict_types=1);

use App\Modules\Reporting\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
    Route::get('reports/sales', [ReportController::class, 'sales']);
    Route::get('reports/sales/export', [ReportController::class, 'export']);
    Route::get('reports/production', [ReportController::class, 'production']);
    Route::get('reports/receivables', [ReportController::class, 'receivables']);
    Route::get('reports/receivables/export', [ReportController::class, 'receivablesExport']);
    Route::get('reports/accounting/export', [ReportController::class, 'accountingExport']);
});
