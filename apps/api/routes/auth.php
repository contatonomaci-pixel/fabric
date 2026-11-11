<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\PasswordController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->group(function () {
    // Web middleware guarantees session and CSRF even without a stateful Origin.
    Route::group([], function () {
        Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('auth/forgot-password', [PasswordController::class, 'forgot'])->middleware('throttle:password');
        Route::post('auth/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:password');
        Route::middleware(['auth:sanctum', 'active', 'throttle:workspace'])->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('auth/logout', [AuthController::class, 'logout']);
            Route::get('companies', [CompanyController::class, 'index']);
            Route::get('companies/{company}', [CompanyController::class, 'show']);
            Route::post('companies', [CompanyController::class, 'store']);
            Route::patch('companies/{company}', [CompanyController::class, 'update']);
            require __DIR__.'/catalog.php';
            require __DIR__.'/inventory.php';
        });
    });
});
