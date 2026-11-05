<?php

use App\Http\Controllers\Finance\CashCloseController;
use App\Http\Controllers\Finance\DuesController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\FinanceController;
use App\Http\Controllers\Finance\ReportController;
use Illuminate\Support\Facades\Route;

// --- finance --- (loaded inside auth + active by routes/web.php)

Route::middleware('permission:finance.view')->prefix('finance')->name('finance.')->group(function () {
    Route::get('/', [FinanceController::class, 'index'])->name('index');

    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/expenses/{expense}/receipt', [ExpenseController::class, 'receipt'])->name('expenses.receipt');

    Route::get('/cash-close', [CashCloseController::class, 'show'])->name('cash-close');
    Route::get('/cash-closes', [CashCloseController::class, 'index'])->name('cash-closes.index');

    Route::get('/dues', DuesController::class)->name('dues');

    Route::middleware('permission:finance.manage')->group(function () {
        Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::post('/expenses/{expense}/cancel', [ExpenseController::class, 'cancel'])->name('expenses.cancel');

        Route::post('/cash-close', [CashCloseController::class, 'store'])->name('cash-close.store');
    });
});

Route::middleware('permission:reports.view')->prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/export', [ReportController::class, 'export'])->name('export');
    Route::get('/register/{type}', [ReportController::class, 'register'])
        ->whereIn('type', ['sales', 'expenses'])->name('register');
});
