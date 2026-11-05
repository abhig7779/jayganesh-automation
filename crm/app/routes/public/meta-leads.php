<?php

use App\Http\Controllers\Leads\MetaLeadsWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

// Meta lead ads webhook: https://crm.jayganeshcaraccessories.com/webhooks/meta-leads
// Off (404) unless META_LEADS_ENABLED=true. No login, no CSRF; the POST is checked by
// X-Hub-Signature-256 with the Meta app secret. Setup: crm/LEADS-API.md.
Route::withoutMiddleware([ValidateCsrfToken::class])
    ->middleware('throttle:120,1')
    ->group(function () {
        Route::get('/webhooks/meta-leads', [MetaLeadsWebhookController::class, 'verify'])->name('meta-leads.webhook');
        Route::post('/webhooks/meta-leads', [MetaLeadsWebhookController::class, 'receive'])->name('meta-leads.webhook.receive');
    });
