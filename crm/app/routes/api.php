<?php

use App\Http\Controllers\Api\V1\CustomerCarController;
use App\Http\Controllers\Api\V1\CustomerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
| JSON API (ARCHITECTURE.md §4). `auth:sanctum` accepts a bearer token (mobile app, tools) or,
| because statefulApi() is on in bootstrap/app.php, the normal web login cookie when the call
| comes from the CRM's own domain (SANCTUM_STATEFUL_DOMAINS). Policies check every action.
*/
Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::get('customers/lookup', [CustomerController::class, 'lookup'])->name('customers.lookup');
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->whereNumber('customer')->name('customers.show');
    Route::patch('customers/{customer}', [CustomerController::class, 'update'])->whereNumber('customer')->name('customers.update');
    Route::post('customers/{customer}/archive', [CustomerController::class, 'archive'])->whereNumber('customer')->name('customers.archive');
    Route::post('customers/{customer}/cars', [CustomerCarController::class, 'store'])->whereNumber('customer')->name('customers.cars.store');
    Route::patch('cars/{car}', [CustomerCarController::class, 'update'])->whereNumber('car')->name('cars.update');
});
