<?php

use App\Http\Controllers\Ops\HealthController;
use Illuminate\Support\Facades\Route;

// System health (DevOps). Already inside auth + active (routes/web.php). Owner only.
Route::get('/health', HealthController::class)->middleware('role:owner')->name('health');
