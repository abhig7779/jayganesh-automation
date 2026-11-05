<?php

use App\Http\Controllers\Imports\ApiTokenController;
use App\Http\Controllers\Imports\CustomerImportController;
use Illuminate\Support\Facades\Route;

// Customer import (Owner/Admin) and API keys for the website lead form (Owner). Loaded inside auth + active.

Route::middleware('role:owner|admin')->group(function () {
    Route::get('/customers/import', [CustomerImportController::class, 'create'])->name('customers.import');
    Route::post('/customers/import', [CustomerImportController::class, 'upload'])->name('customers.import.upload');
    Route::get('/customers/import/template.csv', [CustomerImportController::class, 'template'])->name('customers.import.template');
    Route::get('/customers/import/runs/{import}', [CustomerImportController::class, 'show'])->whereNumber('import')->name('customers.import.show');
    Route::post('/customers/import/runs/{import}', [CustomerImportController::class, 'continue'])->whereNumber('import')->name('customers.import.continue');
    Route::get('/customers/import/{token}', [CustomerImportController::class, 'map'])->where('token', '[A-Za-z0-9]{32}')->name('customers.import.map');
    Route::post('/customers/import/{token}', [CustomerImportController::class, 'start'])->where('token', '[A-Za-z0-9]{32}')->name('customers.import.start');
});

Route::middleware('role:owner')->group(function () {
    Route::get('/settings/api-keys', [ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::post('/settings/api-keys', [ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('/settings/api-keys/{token}', [ApiTokenController::class, 'destroy'])->whereNumber('token')->name('api-tokens.destroy');
});
