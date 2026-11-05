<?php

use App\Http\Controllers\Quotations\FollowUpController;
use App\Http\Controllers\Quotations\LeadController;
use App\Http\Controllers\Quotations\QuotationController;
use Illuminate\Support\Facades\Route;

// Quotations, follow-ups and leads (crm/CONTRACTS.md route names). Loaded inside auth + active.
// Quotations belong to Sales; leads/marketing only to users who can `leads.manage`.

Route::middleware('permission:quotations.view')->group(function () {
    Route::get('/quotations', [QuotationController::class, 'index'])->name('quotations.index');

    Route::middleware('permission:quotations.manage')->group(function () {
        Route::get('/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
        Route::post('/quotations', [QuotationController::class, 'store'])->name('quotations.store');
        Route::get('/quotations/products.json', [QuotationController::class, 'productIndex'])->name('quotations.product-index');
        Route::post('/quotations/{quotation}/convert', [QuotationController::class, 'convert'])->whereNumber('quotation')->name('quotations.convert');
        Route::get('/quotations/{quotation}/whatsapp', [QuotationController::class, 'whatsapp'])->whereNumber('quotation')->name('quotations.whatsapp');
        Route::post('/quotations/{quotation}/lost', [QuotationController::class, 'lost'])->whereNumber('quotation')->name('quotations.lost');
        Route::post('/quotations/{quotation}/reassign', [QuotationController::class, 'reassign'])->whereNumber('quotation')->name('quotations.reassign');
    });

    Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])->whereNumber('quotation')->name('quotations.show');
    Route::get('/quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->whereNumber('quotation')->name('quotations.pdf');
});

// One list for both: quotation follow-ups (quotations.*) and lead follow-ups (leads.manage).
// Each action checks the follow-up's subject in the controller.
Route::middleware('permission:quotations.view|leads.manage')->group(function () {
    Route::get('/followups', [FollowUpController::class, 'index'])->name('followups.index');
    Route::post('/followups/{followUp}/log', [FollowUpController::class, 'log'])->whereNumber('followUp')->name('followups.log');
    Route::post('/followups/{followUp}/undo', [FollowUpController::class, 'undo'])->whereNumber('followUp')->name('followups.undo');
    Route::get('/followups/{followUp}/whatsapp', [FollowUpController::class, 'whatsapp'])->whereNumber('followUp')->name('followups.whatsapp');
    Route::get('/followups/{followUp}/call', [FollowUpController::class, 'call'])->whereNumber('followUp')->name('followups.call');
});

Route::middleware('permission:leads.manage')->group(function () {
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
    Route::post('/leads/{lead}/lost', [LeadController::class, 'lost'])->whereNumber('lead')->name('leads.lost');
    Route::post('/leads/{lead}/reassign', [LeadController::class, 'reassign'])->whereNumber('lead')->name('leads.reassign');
    // "Asked price" quick log: a price enquiry lead (UX-FLOWS §3.2 step 1).
    Route::post('/quotations/asked-price', [QuotationController::class, 'askedPrice'])->name('quotations.asked-price');
    Route::get('/leads/products.json', [QuotationController::class, 'productIndex'])->name('leads.product-index');
});
