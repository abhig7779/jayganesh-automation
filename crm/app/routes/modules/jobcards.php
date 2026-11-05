<?php

use App\Http\Controllers\JobCards\JobCardActionController;
use App\Http\Controllers\JobCards\JobCardController;
use App\Http\Controllers\JobCards\JobCardPhotoController;
use Illuminate\Support\Facades\Route;

// Job cards module (crm/CONTRACTS.md). Loaded inside auth + active by routes/web.php.
// Access is checked per action by App\Policies\JobCardPolicy.
Route::prefix('jobcards')->name('jobcards.')->group(function () {
    Route::get('/', [JobCardController::class, 'index'])->name('index');
    Route::get('/mine', [JobCardController::class, 'mine'])->name('mine');
    Route::get('/create', [JobCardController::class, 'create'])->name('create');
    Route::post('/', [JobCardController::class, 'store'])->name('store');
    Route::get('/products', [JobCardController::class, 'products'])->name('products');

    Route::whereNumber(['jobCard', 'item', 'photo'])->group(function () {
        Route::get('/{jobCard}', [JobCardController::class, 'show'])->name('show');
        Route::get('/{jobCard}/print', [JobCardController::class, 'print'])->name('print');
        Route::post('/{jobCard}/status', [JobCardActionController::class, 'status'])->name('status');
        Route::post('/{jobCard}/assign', [JobCardActionController::class, 'assign'])->name('assign');
        Route::post('/{jobCard}/items', [JobCardActionController::class, 'addItem'])->name('items.store');
        Route::post('/{jobCard}/items/{item}/toggle', [JobCardActionController::class, 'toggleItem'])->name('items.toggle');
        Route::post('/{jobCard}/bill', [JobCardActionController::class, 'makeBill'])->name('bill');
        Route::post('/{jobCard}/notify-ready', [JobCardActionController::class, 'notifyReady'])->name('notify-ready');
        Route::post('/{jobCard}/photos', [JobCardPhotoController::class, 'store'])->name('photos.store');
        Route::get('/{jobCard}/photos/{photo}', [JobCardPhotoController::class, 'show'])->name('photos.show');
    });
});
