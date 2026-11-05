<?php

use App\Http\Controllers\WhatsApp\WebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

// Meta WhatsApp webhook: https://crm.jayganeshcaraccessories.com/webhooks/whatsapp
// No login and no CSRF token (Meta can't send one); the POST is checked by X-Hub-Signature-256.
Route::withoutMiddleware([ValidateCsrfToken::class])
    ->middleware('throttle:600,1')
    ->group(function () {
        Route::get('/webhooks/whatsapp', [WebhookController::class, 'verify'])->name('whatsapp.webhook');
        Route::post('/webhooks/whatsapp', [WebhookController::class, 'receive'])->name('whatsapp.webhook.receive');
    });
