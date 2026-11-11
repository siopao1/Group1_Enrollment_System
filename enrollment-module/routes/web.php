<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\CourseOfferingController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\StudentRecordController;
use App\Http\Controllers\SubjectController;
use Illuminate\Support\Facades\Route;

/*
| Who may do what (same rules as the main app's roles.js):
|   Subjects, Course Offerings   view: everyone · add/edit/delete: admin
|   Enroll Student, Enrollment Records   view/add/edit: admin, registrar · delete: admin
|   Student Records              view: everyone
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

// Single sign-on with the main app (one-time codes; see SsoController)
Route::get('/sso', [SsoController::class, 'consume'])->name('sso.consume');
Route::get('/go-main', [SsoController::class, 'goMain'])->middleware('auth')->name('sso.go-main');

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/offerings');

    // Admin only: add, edit, delete subjects and offerings.
    // (Registered before the read-only routes so /subjects/create is not read as /subjects/{subject}.)
    Route::middleware('role:admin')->group(function () {
        Route::resource('subjects', SubjectController::class)->except(['index', 'show']);
        Route::resource('offerings', CourseOfferingController::class)->except(['index', 'show']);
    });

    // Admin and registrar: enroll students, view and edit enrollments.
    Route::middleware('role:admin,registrar')->group(function () {
        Route::resource('enrollments', EnrollmentController::class)->except(['destroy']);
    });
    // Admin only: delete an enrollment.
    Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])
        ->middleware('role:admin')->name('enrollments.destroy');

    // Every role: view
    Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
    Route::get('/offerings', [CourseOfferingController::class, 'index'])->name('offerings.index');
    Route::get('/offerings/{offering}', [CourseOfferingController::class, 'show'])->name('offerings.show');
    Route::get('/records', [StudentRecordController::class, 'index'])->name('records.index');
    Route::get('/records/{student}', [StudentRecordController::class, 'show'])->name('records.show');
});
