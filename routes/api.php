<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StrapiWebhookController;
use App\Http\Controllers\StripeWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Strapi Webhooks (no auth - secured by webhook secret)
Route::prefix('webhooks/strapi')->group(function () {
    Route::post('/content-published', [StrapiWebhookController::class, 'handleContentPublished']);
    Route::post('/content-unpublished', [StrapiWebhookController::class, 'handleContentUnpublished']);
});

// Stripe webhooks (no auth - secured by signature). Not throttled: Stripe
// retries and bursts, and a 429 would make it back off our real events.
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->withoutMiddleware('throttle:api')
    ->name('webhooks.stripe');

// Admin routes (requires authentication)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/strapi/clear-cache', [StrapiWebhookController::class, 'clearAllCache']);
});
