<?php

use App\Http\Controllers\Purchases\PurchaseController;
use App\Http\Controllers\Purchases\SupplierController;
use Illuminate\Support\Facades\Route;

// --- purchases --- Stock in from suppliers (UX-FLOWS §3.6). Loaded inside auth + active.
// Owner, Admin and Finance (purchases.manage). Cancelling a purchase is Owner/Admin only (checked in the controller).

Route::middleware('permission:purchases.manage')->group(function () {
    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('/purchases/products.json', [PurchaseController::class, 'products'])->name('purchases.product-index');
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->whereNumber('purchase')->name('purchases.show');
    Route::get('/purchases/{purchase}/photo', [PurchaseController::class, 'photo'])->whereNumber('purchase')->name('purchases.photo');
    Route::post('/purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->whereNumber('purchase')->name('purchases.cancel');

    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->whereNumber('supplier')->name('suppliers.show');
    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->whereNumber('supplier')->name('suppliers.edit');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->whereNumber('supplier')->name('suppliers.update');
    Route::post('/suppliers/{supplier}/payments', [SupplierController::class, 'pay'])->whereNumber('supplier')->name('suppliers.payments.store');
});
