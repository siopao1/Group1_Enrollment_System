<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CourseOfferingController;
use App\Http\Controllers\SubjectController;
use Illuminate\Support\Facades\Route;

/*
| Who may do what (same rules as the main app's roles.js):
|   Subjects, Course Offerings   view: everyone · add/edit/delete: admin
|
| 'auth' = must be signed in. 'role:...' = EnsureRole middleware (403 otherwise).
| Every form also carries a CSRF token (@csrf), checked by Laravel on POST/PUT/DELETE.
*/

// Sign in / out
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/offerings');

    // Admin only: add, edit, delete subjects and offerings.
    // (Registered before the read-only routes so /subjects/create is not read as /subjects/{subject}.)
    Route::middleware('role:admin')->group(function () {
        Route::resource('subjects', SubjectController::class)->except(['index', 'show']);
        Route::resource('offerings', CourseOfferingController::class)->except(['index', 'show']);
    });

    // Every role: view
    Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
    Route::get('/offerings', [CourseOfferingController::class, 'index'])->name('offerings.index');
    Route::get('/offerings/{offering}', [CourseOfferingController::class, 'show'])->name('offerings.show');
});
