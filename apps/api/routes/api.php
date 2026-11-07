<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::get('v1/health', HealthController::class)->withoutMiddleware(EnsureFrontendRequestsAreStateful::class)->middleware('throttle:30,1');
