<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// --- profile + activity log --- Loaded inside auth + active.

// My profile: every signed-in person changes their own name and password here.
Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

// Activity log: who changed what, when (Owner, Admin).
Route::get('/activity', ActivityController::class)->middleware('permission:audit.view')->name('activity.index');
