<?php

declare(strict_types=1);

use App\Modules\Reporting\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
    Route::get('reports/sales', [ReportController::class, 'sales']);
    Route::get('reports/sales/export', [ReportController::class, 'export']);
});
