<?php

use App\Http\Controllers\Bills\BillController;
use App\Http\Controllers\Bills\CollectPaymentController;
use App\Http\Controllers\Bills\DraftController;
use App\Http\Controllers\Bills\ProductIndexController;
use Illuminate\Support\Facades\Route;

// Billing module (crm/CONTRACTS.md). Loaded inside auth + active by routes/web.php.

Route::middleware('permission:invoices.create')->group(function () {
    Route::get('/bills/new', [BillController::class, 'create'])->name('bills.create');
    Route::post('/bills', [BillController::class, 'store'])->middleware('throttle:60,1')->name('bills.store');
    Route::get('/bills/customer-context', [BillController::class, 'customerContext'])->name('bills.customer-context');
    Route::get('/bills/model-suggestions', [BillController::class, 'modelSuggestionsJson'])->name('bills.model-suggestions');
    Route::get('/bills/draft', [DraftController::class, 'show'])->name('bills.draft');
    Route::put('/bills/draft', [DraftController::class, 'save'])->name('bills.draft.save');
    Route::delete('/bills/draft', [DraftController::class, 'destroy'])->name('bills.draft.destroy');
});

Route::get('/bills/upi-qr', [BillController::class, 'upiQr'])
    ->middleware('permission:invoices.create|payments.collect')
    ->name('bills.upi-qr');

// The instant product search index; quotations reuses it.
Route::get('/bills/product-index', ProductIndexController::class)
    ->middleware('permission:invoices.create|quotations.manage')
    ->name('bills.product-index');

Route::middleware('permission:invoices.view')->group(function () {
    Route::get('/bills', [BillController::class, 'index'])->name('bills.index');
    Route::get('/bills/{invoice}', [BillController::class, 'show'])->whereNumber('invoice')->name('bills.show');
    Route::get('/bills/{invoice}/done', [BillController::class, 'done'])->whereNumber('invoice')->name('bills.done');
    Route::get('/bills/{invoice}/status', [BillController::class, 'status'])->whereNumber('invoice')->name('bills.status');
    Route::get('/bills/{invoice}/print', [BillController::class, 'print'])->whereNumber('invoice')->name('bills.print');
    Route::get('/bills/{invoice}/pdf', [BillController::class, 'pdf'])->whereNumber('invoice')->name('bills.pdf');
});

Route::middleware('permission:invoices.cancel')->group(function () {
    Route::post('/bills/{invoice}/cancel', [BillController::class, 'cancel'])->whereNumber('invoice')->name('bills.cancel');
    Route::post('/bills/{invoice}/return', [BillController::class, 'returnItems'])->whereNumber('invoice')->name('bills.return');
});

Route::middleware('permission:payments.collect')->group(function () {
    Route::get('/payments/collect', [CollectPaymentController::class, 'create'])->name('payments.collect');
    Route::post('/payments/collect', [CollectPaymentController::class, 'store'])->name('payments.collect.store');
});
Route::get('/payments/{payment}/receipt', [CollectPaymentController::class, 'receipt'])->whereNumber('payment')->name('payments.receipt');
