<?php

use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

/*
| App shell (crm/CONTRACTS.md): global search. GET /search?q= returns the full page, or just the
| grouped results when htmx asks (the top-bar dropdown). Results are filtered by permission.
*/
Route::get('/search', SearchController::class)->middleware('throttle:120,1')->name('search');
