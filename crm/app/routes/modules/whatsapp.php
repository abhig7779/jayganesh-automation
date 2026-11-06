<?php

use App\Http\Controllers\WhatsApp\ConnectController;
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

    // Connect the shop number (Embedded Signup, coexistence) and the AI reply switch: Owner only.
    Route::middleware('role:owner')->group(function () {
        Route::get('/connect', [ConnectController::class, 'show'])->name('connect');
        Route::post('/connect', [ConnectController::class, 'store'])->middleware('throttle:10,1')->name('connect.store');
        Route::post('/connect/event', [ConnectController::class, 'event'])->middleware('throttle:60,1')->name('connect.event');
        Route::post('/connect/sync', [ConnectController::class, 'sync'])->middleware('throttle:5,1')->name('connect.sync');
        Route::post('/ai', [ConnectController::class, 'ai'])->name('ai');
    });
});
