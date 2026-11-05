<?php

use App\Http\Controllers\Customers\CustomerCarController;
use App\Http\Controllers\Customers\CustomerController;
use App\Models\Customer;
use Illuminate\Support\Facades\Route;

/*
| Shell + customers UI (crm/CONTRACTS.md). Loaded inside auth + active by routes/web.php.
| Fixed names: customers.index/show/create/store/edit/update. The extra names below
| (customers.check, .archive, .restore, .notes, .cars.*) belong to this module too.
*/

Route::middleware('can:viewAny,'.Customer::class)->group(function () {
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    // Duplicate check while typing the mobile number (htmx partial).
    Route::get('/customers/check', [CustomerController::class, 'check'])->name('customers.check');
});

Route::middleware('can:create,'.Customer::class)->group(function () {
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
});

Route::whereNumber(['customer', 'car'])->group(function () {
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('can:view,customer')->name('customers.show');

    Route::middleware('can:update,customer')->group(function () {
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::put('/customers/{customer}/notes', [CustomerController::class, 'notes'])->name('customers.notes');
        Route::post('/customers/{customer}/cars', [CustomerCarController::class, 'store'])->name('customers.cars.store');
        Route::put('/customers/{customer}/cars/{car}', [CustomerCarController::class, 'update'])->scopeBindings()->name('customers.cars.update');
    });

    Route::middleware('can:archive,customer')->group(function () {
        Route::post('/customers/{customer}/archive', [CustomerController::class, 'archive'])->name('customers.archive');
        Route::post('/customers/{customer}/restore', [CustomerController::class, 'restore'])->name('customers.restore');
    });
});
