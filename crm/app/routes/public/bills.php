<?php

use App\Http\Controllers\Bills\PublicBillController;
use Illuminate\Support\Facades\Route;

// Signed, expiring link to a bill PDF for the customer (WhatsApp e-bill). No login needed.
Route::get('/b/{invoice}/pdf', PublicBillController::class)
    ->whereNumber('invoice')
    ->middleware(['signed', 'throttle:30,1'])
    ->name('bills.public-pdf');
