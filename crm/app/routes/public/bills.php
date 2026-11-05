<?php

use App\Http\Controllers\Bills\PublicBillController;
use Illuminate\Support\Facades\Route;

// Signed, expiring link to a bill PDF for the customer (WhatsApp e-bill). No login needed.
Route::get('/b/{invoice}/pdf', PublicBillController::class)
    ->whereNumber('invoice')
    ->middleware(['signed', 'throttle:30,1'])
    ->name('bills.public-pdf');

// Noto Sans (OFL) for the printable bill pages (thermal receipt, payment receipt). Only the
// three files in resources/fonts are served; browsers cache them for a year.
Route::get('/bill-fonts/{file}', function (string $file) {
    return response()->file(resource_path('fonts/'.$file), [
        'Content-Type' => 'font/ttf',
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('file', 'NotoSans-(Regular|Medium|SemiBold)\.ttf')->name('bills.font');
