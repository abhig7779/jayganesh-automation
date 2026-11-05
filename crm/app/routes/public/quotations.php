<?php

use App\Http\Controllers\Quotations\QuotationController;
use Illuminate\Support\Facades\Route;

// The quotation PDF link that goes inside WhatsApp messages: no login, but a signed,
// time-limited URL (FollowUpMessages::pdfLink), so numbers can't be guessed.
Route::get('/q/{quotation}.pdf', [QuotationController::class, 'publicPdf'])
    ->whereNumber('quotation')
    ->middleware(['signed', 'throttle:60,1'])
    ->name('quotations.public-pdf');
