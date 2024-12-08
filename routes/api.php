<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Tutor\TutorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth Controller
Route::controller(AuthController::class)->group(function () {
    Route::post('/login', 'login');
    Route::post('/register', 'register');
    Route::get('/logout', 'logout')->middleware('jwt.auth');
    Route::get('/verify-email', 'verifyEmail');
    Route::get('/resentOtp', 'resendOtp');
    Route::post('reset-password', 'resetPassword');

    Route::middleware('jwt.auth')->group(function () {
        Route::get('/user', 'getUser');
        Route::put('/update-profile', 'updateProfile');
        Route::put('/update-password', 'updatePassword');
    });
});

// Tutor Controller
Route::prefix('tutor')->controller(TutorController::class)->group(function () {
    Route::middleware(['jwt.auth', 'tutor',])->group(function () {
        Route::get('/', 'getTutor');
        Route::put('/update-profile', 'updateTutorProfile');
        Route::post('/verify-tutor-info', 'verifyTutorInfo');
        Route::get('/verify-tutor-info', 'getTutorVerificationInfo');
    });
});
