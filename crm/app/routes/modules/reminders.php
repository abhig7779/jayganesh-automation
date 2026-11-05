<?php

use App\Http\Controllers\Reminders\ReminderController;
use App\Http\Controllers\Reminders\ReminderRuleController;
use Illuminate\Support\Facades\Route;

// Reminders & reviews: service check-ups and Google review requests. Loaded inside auth + active.
// reminders.manage = Owner, Admin, Marketing (not the counter, owner rule 5 Nov 2026).

Route::middleware('permission:reminders.manage')->group(function () {
    Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders.index');
    Route::get('/reminders/{reminder}/whatsapp', [ReminderController::class, 'whatsapp'])->whereNumber('reminder')->name('reminders.whatsapp');
    Route::post('/reminders/{reminder}/send', [ReminderController::class, 'send'])->whereNumber('reminder')->name('reminders.send');
    Route::post('/reminders/{reminder}/done', [ReminderController::class, 'done'])->whereNumber('reminder')->name('reminders.done');
    Route::post('/reminders/{reminder}/skip', [ReminderController::class, 'skip'])->whereNumber('reminder')->name('reminders.skip');

    // Rules are the Owner's (Settings permission).
    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/reminders/rules', [ReminderRuleController::class, 'index'])->name('reminders.rules.index');
        Route::post('/reminders/rules', [ReminderRuleController::class, 'store'])->name('reminders.rules.store');
        Route::get('/reminders/rules/{rule}/edit', [ReminderRuleController::class, 'edit'])->whereNumber('rule')->name('reminders.rules.edit');
        Route::put('/reminders/rules/{rule}', [ReminderRuleController::class, 'update'])->whereNumber('rule')->name('reminders.rules.update');
    });
});
