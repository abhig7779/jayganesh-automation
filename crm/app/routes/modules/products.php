<?php

use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Products\ProductImportController;
use App\Http\Controllers\Products\StockCheckController;
use Illuminate\Support\Facades\Route;

// Products, stock and the price list (crm/CONTRACTS.md route names). Loaded inside auth + active.

Route::middleware('permission:inventory.manage')->group(function () {
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->whereNumber('product')->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->whereNumber('product')->name('products.update');
    Route::post('/products/{product}/adjust', [ProductController::class, 'adjust'])->whereNumber('product')->name('products.adjust');

    Route::get('/products/import', [ProductImportController::class, 'create'])->name('products.import');
    Route::post('/products/import', [ProductImportController::class, 'upload'])->name('products.import.upload');
    Route::get('/products/import/template.csv', [ProductImportController::class, 'template'])->name('products.import.template');
    Route::get('/products/import/{token}', [ProductImportController::class, 'map'])->where('token', '[A-Za-z0-9]{32}')->name('products.import.map');
    Route::post('/products/import/{token}', [ProductImportController::class, 'commit'])->where('token', '[A-Za-z0-9]{32}')->name('products.import.commit');
});

Route::middleware('permission:inventory.view')->group(function () {
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [ProductController::class, 'show'])->whereNumber('product')->name('products.show');
    Route::get('/stock/check', StockCheckController::class)->name('stock.check');
});
