<?php

use App\Http\Controllers\Api\V1\CatalogApiController;
use App\Http\Controllers\Api\V1\PixWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/products', [CatalogApiController::class, 'products'])->name('products.index');
    Route::get('/products/{product:slug}', [CatalogApiController::class, 'showProduct'])->name('products.show');
    Route::get('/categories', [CatalogApiController::class, 'categories'])->name('categories.index');
    Route::get('/shipping/quote', [CatalogApiController::class, 'shippingQuote'])
        ->middleware('throttle:cep-lookup')
        ->name('shipping.quote');

    Route::post('/webhooks/pix', PixWebhookController::class)
        ->middleware('throttle:60,1')
        ->name('webhooks.pix');
});
