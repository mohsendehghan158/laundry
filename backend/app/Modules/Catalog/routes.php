<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\CatalogController;
use App\Modules\Catalog\Http\Controllers\ColorController;
use App\Modules\Catalog\Http\Controllers\DefectTypeController;
use App\Modules\Catalog\Http\Controllers\ItemCategoryController;
use App\Modules\Catalog\Http\Controllers\ItemController;
use App\Modules\Catalog\Http\Controllers\PriceController;
use App\Modules\Catalog\Http\Controllers\PriceListController;
use App\Modules\Catalog\Http\Controllers\ServiceTypeController;
use App\Modules\Catalog\Http\Controllers\TurnaroundTierController;
use App\Modules\Catalog\Http\Controllers\UpchargeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('catalog', CatalogController::class);

    Route::apiResource('price-lists', PriceListController::class)->except('show');
    Route::get('prices', [PriceController::class, 'index']);
    Route::put('prices', [PriceController::class, 'update']);
    Route::get('prices/export', [PriceController::class, 'export']);
    Route::post('prices/import', [PriceController::class, 'import']);

    Route::apiResource('service-types', ServiceTypeController::class)->except('show');
    Route::apiResource('item-categories', ItemCategoryController::class)->except('show');
    Route::apiResource('items', ItemController::class)->except('show');
    Route::apiResource('upcharges', UpchargeController::class)->except('show');
    Route::apiResource('turnaround-tiers', TurnaroundTierController::class)->except('show');
    Route::apiResource('defect-types', DefectTypeController::class)->except('show');
    Route::apiResource('colors', ColorController::class)->except('show');
});
