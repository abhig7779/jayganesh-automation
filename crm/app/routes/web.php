<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:20,1');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::middleware('permission:staff.manage')->group(function () {
        Route::resource('staff', StaffController::class)->except(['show', 'destroy'])
            ->parameters(['staff' => 'staff']);
    });

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    // Each module owns one file in routes/modules/ (customers.php, products.php, bills.php,
    // quotations.php, whatsapp.php …). They load here, already inside auth + active, so
    // modules never edit this file. See crm/CONTRACTS.md.
    foreach (glob(__DIR__.'/modules/*.php') as $moduleRoutes) {
        require $moduleRoutes;
    }
});

// Public, unauthenticated module routes (e.g. the WhatsApp webhook, signed PDF links).
foreach (glob(__DIR__.'/public/*.php') as $publicRoutes) {
    require $publicRoutes;
}
