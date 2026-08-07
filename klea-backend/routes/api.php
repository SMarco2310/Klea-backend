<?php

use App\Http\Controllers\ApiKeysController;
use App\Http\Controllers\ApplicationsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\FeaturesController;
use App\Http\Controllers\PlansController;
use App\Http\Controllers\Public\PlansController as PublicPlansController;
use App\Http\Controllers\Public\SemoaCallbackController;
use App\Http\Controllers\Public\SubscribeController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SubscribersController;
use App\Http\Controllers\SubscriptionsController;
use App\Http\Controllers\TenantInvitationsController;
use App\Http\Controllers\TenantsController;
use App\Http\Controllers\TransactionsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookLogsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/clerk', [AuthController::class, 'loginWithClerk']);

Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['auth:sanctum', 'signed'])
    ->name('verification.verify');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [UserController::class, 'show']);
    Route::patch('/me', [UserController::class, 'update']);
    Route::post('/me/switch-tenant', [UserController::class, 'switchTenant']);

    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend']);

    Route::apiResource('tenants', TenantsController::class)->except(['store']);

    Route::post('/tenant-invitations/{token}/accept', [TenantInvitationsController::class, 'accept']);
    Route::post('/tenant-invitations/{token}/decline', [TenantInvitationsController::class, 'decline']);
    Route::apiResource('tenant-invitations', TenantInvitationsController::class)->except(['create', 'edit', 'update']);

    // Everything below is scoped to $request->user()->current_tenant_id, so
    // it needs current_tenant_id to actually be a tenant the user still
    // belongs to. tenant.member self-heals that before the controller runs
    // (falls back to another tenant, or 409s if the user has none left).
    Route::middleware('tenant.member')->group(function () {
        Route::apiResource('applications', ApplicationsController::class);
        Route::apiResource('features', FeaturesController::class);

        Route::post('/plans/reorder', [PlansController::class, 'reorder']);
        Route::apiResource('plans', PlansController::class);
        Route::post('/plans/{plan}/features', [PlansController::class, 'attachFeature']);
        Route::delete('/plans/{plan}/features/{feature}', [PlansController::class, 'detachFeature']);

        Route::apiResource('api-keys', ApiKeysController::class)->except(['create', 'edit', 'update']);

        Route::get('/subscribers', [SubscribersController::class, 'index']);
        Route::get('/subscribers/{subscriber}', [SubscribersController::class, 'show']);
        Route::patch('/subscribers/{subscriber}', [SubscribersController::class, 'update']);

        Route::get('/subscriptions', [SubscriptionsController::class, 'index']);
        Route::get('/subscriptions/{subscription}', [SubscriptionsController::class, 'show']);
        Route::delete('/subscriptions/{subscription}', [SubscriptionsController::class, 'destroy']);

        Route::get('/transactions', [TransactionsController::class, 'index']);
        Route::get('/transactions/summary', [TransactionsController::class, 'summary']);
        Route::get('/transactions/{transaction}', [TransactionsController::class, 'show']);

        Route::get('/webhook-logs', [WebhookLogsController::class, 'index']);
        Route::get('/webhook-logs/{webhookLog}', [WebhookLogsController::class, 'show']);
    });
});

// Public API for external apps (authenticated via API key, not user session)
Route::middleware('api.key')->prefix('public')->group(function () {
    Route::get('/plans', [PublicPlansController::class, 'index']);
    Route::post('/subscribe', SubscribeController::class);
});

// Semoa payment callback (server-to-server, no user/api-key auth — verified via JWT signature instead)
Route::post('/public/semoa/callback/{tenant}', SemoaCallbackController::class);
