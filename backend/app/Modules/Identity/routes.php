<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\RoleController;
use App\Modules\Identity\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('me', [MeController::class, 'show']);
    Route::put('me/password', [MeController::class, 'updatePassword']);

    Route::apiResource('users', UserController::class);

    Route::get('permissions', [RoleController::class, 'permissions']);
    Route::get('roles', [RoleController::class, 'index']);
    Route::put('roles/{role}', [RoleController::class, 'update']);
});
