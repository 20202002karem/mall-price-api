<?php

use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\PriceController as AdminPriceController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\PriceController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);

    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/barcode/{barcode}', [ProductController::class, 'byBarcode']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::get('products/{product}/price', [PriceController::class, 'current']);

    Route::prefix('admin')->group(function () {

        Route::post('login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);

            Route::get('categories', [AdminCategoryController::class, 'index']);
            Route::post('categories', [AdminCategoryController::class, 'store']);
            Route::get('categories/{category}', [AdminCategoryController::class, 'show']);
            Route::put('categories/{category}', [AdminCategoryController::class, 'update']);
            Route::patch('categories/{category}', [AdminCategoryController::class, 'update']);
            Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy']);

            Route::get('products', [AdminProductController::class, 'index']);
            Route::post('products', [AdminProductController::class, 'store']);
            Route::get('products/{product}', [AdminProductController::class, 'show']);
            Route::put('products/{product}', [AdminProductController::class, 'update']);
            Route::patch('products/{product}', [AdminProductController::class, 'update']);
            Route::delete('products/{product}', [AdminProductController::class, 'destroy']);
            Route::patch('products/{product}/activate', [AdminProductController::class, 'activate']);
            Route::patch('products/{product}/deactivate', [AdminProductController::class, 'deactivate']);

            Route::prefix('products/{product}/prices')->group(function () {
                Route::get('/', [AdminPriceController::class, 'index']);
                Route::post('/', [AdminPriceController::class, 'store']);
                Route::get('/current', [AdminPriceController::class, 'current']); // قبل {price} عمدًا
                Route::get('/{price}', [AdminPriceController::class, 'show']);
                Route::put('/{price}', [AdminPriceController::class, 'update']);
                Route::patch('/{price}', [AdminPriceController::class, 'update']);
                Route::delete('/{price}', [AdminPriceController::class, 'destroy']);
            });
        });
    });
});
