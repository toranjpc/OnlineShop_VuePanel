<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\CategoryController;
use Modules\Product\Http\Controllers\WarehouseController;
use Modules\Product\Http\Controllers\ProductController;
use Modules\Product\Http\Controllers\AccountingController;


Route::prefix('products')
    ->name('products.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function () {

        Route::prefix('categories')
            ->name('categories.')
            ->group(function () {
                Route::post('/list', [CategoryController::class, 'categoryList'])->name('categoryList');
                Route::post('/search', [CategoryController::class, 'categorySearch'])->name('categorySearch');
                Route::post('/store', [CategoryController::class, 'categoryStore'])->name('categoryStore');
                Route::post('/view/{id}', [CategoryController::class, 'categoryView'])->name('categoryView');
                Route::post('/update/{id}', [CategoryController::class, 'categoryUpdate'])->name('categoryUpdate');
                Route::post('/delete/{id}', [CategoryController::class, 'categoryDelete'])->name('categoryDelete');
                Route::post('/force-delete/{id}', [CategoryController::class, 'categoryForceDelete'])->name('categoryForceDelete');
                Route::post('/restore/{id}', [CategoryController::class, 'categoryRestore'])->name('categoryRestore');
            });

        Route::prefix('warehouses')
            ->name('warehouses.')
            ->group(function () {
                Route::post('/list', [WarehouseController::class, 'warehouseList'])->name('warehouseList');
                Route::post('/search', [WarehouseController::class, 'warehouseSearch'])->name('warehouseSearch');
                Route::post('/store', [WarehouseController::class, 'warehouseStore'])->name('warehouseStore');
                Route::post('/view/{id}', [WarehouseController::class, 'warehouseView'])->name('warehouseView');
                Route::post('/update/{id}', [WarehouseController::class, 'warehouseUpdate'])->name('warehouseUpdate');
                Route::post('/delete/{id}', [WarehouseController::class, 'warehouseDelete'])->name('warehouseDelete');
                Route::post('/force-delete/{id}', [WarehouseController::class, 'warehouseForceDelete'])->name('warehouseForceDelete');
                Route::post('/restore/{id}', [WarehouseController::class, 'warehouseRestore'])->name('warehouseRestore');
            });



        Route::post('/list', [ProductController::class, 'productList'])->name('productList');
        Route::post('/search', [ProductController::class, 'productSearch'])->name('productSearch');
        Route::post('/store', [ProductController::class, 'productStore'])->name('productStore');
        Route::post('/view/{id}', [ProductController::class, 'productView'])->name('productView');
        Route::post('/update/{id}', [ProductController::class, 'productUpdate'])->name('productUpdate');
        Route::post('/delete/{id}', [ProductController::class, 'productDelete'])->name('productDelete');
        Route::post('/force-delete/{id}', [ProductController::class, 'productForceDelete'])->name('productForceDelete');
        Route::post('/restore/{id}', [ProductController::class, 'productRestore'])->name('productRestore');
    });

Route::prefix('accounting')
    ->name('accounting.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function () {
        Route::get('next-invoice-number', [AccountingController::class, 'nextInvoiceNumber'])->name('nextInvoiceNumber');
    });
