<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LimitController;
use App\Http\Controllers\Api\ScenePulseController;
use App\Http\Controllers\Api\UserSettingsController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Public Health Check Endpoint (Dokploy & Monitoring)
Route::get('/health', HealthController::class);

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    // --- PUBLIC SCENEPULSE BY FLOWNIE ROUTES (ZERO LOGIN FRICTION + TIME-BUCKET CACHE SHIELD) ---
    Route::get('/scenes/catalog', [ScenePulseController::class, 'catalog']);
    Route::get('/scenes/videos/{idOrSlug}', [ScenePulseController::class, 'showVideo']);
    Route::post('/scenes/analyze', [ScenePulseController::class, 'analyzePause'])->middleware('throttle:30,1');
    Route::get('/qloo/explore', [ScenePulseController::class, 'exploreQloo'])->middleware('throttle:30,1');

    // Optional Auth & Webhook Routes
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:15,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:15,1');
    Route::get('/credits/packages', [CreditController::class, 'packages']);
    Route::post('/webhooks/whop', [WebhookController::class, 'handleWhop']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/auth/user', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::put('/auth/password', [AuthController::class, 'updatePassword']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/user/settings', [UserSettingsController::class, 'show']);
        Route::put('/user/settings', [UserSettingsController::class, 'update']);

        Route::get('/limits', [LimitController::class, 'index']);
        Route::get('/limits/overview', [LimitController::class, 'overview']);
        Route::get('/limits/{resourceType}', [LimitController::class, 'show']);
        Route::get('/limits/{resourceType}/can-create', [LimitController::class, 'canCreate']);

        Route::get('/credits/balance', [CreditController::class, 'balance']);
        Route::get('/credits/orders', [CreditController::class, 'orders']);
        Route::get('/credits/orders/{orderNumber}', [CreditController::class, 'orderStatus']);
        Route::post('/credits/checkout', [CreditController::class, 'createCheckout']);
    });
});
