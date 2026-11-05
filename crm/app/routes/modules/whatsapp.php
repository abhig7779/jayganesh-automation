<?php

use App\Http\Controllers\WhatsApp\MessageLogController;
use Illuminate\Support\Facades\Route;

// WhatsApp module (crm/CONTRACTS.md). Loaded inside auth + active by routes/web.php.

Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
    Route::get('/messages', [MessageLogController::class, 'index'])
        ->middleware('role:owner|admin')->name('messages.index');

    // Counter staff can resend / open a bill's message from the bill screen
    // (<x-whatsapp.status>), so these also allow anyone who can make bills.
    Route::middleware('role_or_permission:owner|admin|invoices.create')->group(function () {
        Route::post('/messages/{log}/resend', [MessageLogController::class, 'resend'])->whereNumber('log')->name('resend');
        Route::post('/messages/{log}/manual', [MessageLogController::class, 'manual'])->whereNumber('log')->name('manual');
    });

    Route::post('/test', [MessageLogController::class, 'test'])
        ->middleware(['role:owner', 'throttle:10,1'])->name('test');
});
