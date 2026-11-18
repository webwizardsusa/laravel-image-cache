<?php

use Illuminate\Support\Facades\Route;
use Webwizardsusa\ImageCache\Http\Controllers\VariantController;

Route::prefix(config('image-cache.route.prefix', 'image-cache'))
    ->middleware(config('image-cache.route.middleware', []))
    ->group(function (): void {
        Route::get('{style}/{image}', VariantController::class)->name(config('image-cache.route.name', 'image-cache.variant'));
    });
