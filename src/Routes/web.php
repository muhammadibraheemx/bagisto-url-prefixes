<?php

use Illuminate\Support\Facades\Route;
use Verasole\Storefront\Http\Controllers\CollectionController;
use Verasole\Storefront\Http\Controllers\ProductController;

Route::get(trim(config('storefront.category_prefix'), '/').'/{slug}', [CollectionController::class, 'show'])
    ->name('shop.collection.show');

Route::get(trim(config('storefront.product_prefix'), '/').'/{slug}', [ProductController::class, 'show'])
    ->name('shop.product_or_category.index');
