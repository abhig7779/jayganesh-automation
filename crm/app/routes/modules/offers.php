<?php

use App\Http\Controllers\Offers\AudienceController;
use App\Http\Controllers\Offers\OfferController;
use App\Http\Controllers\Offers\SegmentController;
use Illuminate\Support\Facades\Route;

// Offers / campaigns, saved segments and the Meta Custom Audience export (CRM-PLAN.md §3.10–11).
// Already inside auth + active (routes/web.php). Gate on the permission only, never a role name:
// Owner, Admin and Marketing have offers.manage, and the Owner can grant it to one person.
Route::middleware('permission:offers.manage')->group(function () {
    Route::get('/offers', [OfferController::class, 'index'])->name('offers.index');
    Route::get('/offers/new', [OfferController::class, 'create'])->name('offers.create');
    Route::get('/offers/audience-count', [OfferController::class, 'count'])->name('offers.count');
    Route::post('/offers/preview', [OfferController::class, 'preview'])->name('offers.preview');
    Route::post('/offers', [OfferController::class, 'store'])->name('offers.store');
    Route::get('/offers/{offer}', [OfferController::class, 'show'])->name('offers.show');
    Route::post('/offers/{offer}/start', [OfferController::class, 'start'])->name('offers.start');
    Route::get('/offers/{offer}/broadcast-list', [OfferController::class, 'broadcast'])->name('offers.broadcast');
    Route::get('/offers/{offer}/send', [OfferController::class, 'assist'])->name('offers.assist');
    Route::post('/offers/{offer}/recipients/{recipient}', [OfferController::class, 'mark'])
        ->scopeBindings()->name('offers.recipients.mark');

    Route::post('/segments', [SegmentController::class, 'store'])->name('segments.store');
    Route::delete('/segments/{segment}', [SegmentController::class, 'destroy'])->name('segments.destroy');

    Route::get('/audiences', [AudienceController::class, 'index'])->name('audiences.export');
    Route::post('/audiences/download', [AudienceController::class, 'download'])->name('audiences.download');
});
