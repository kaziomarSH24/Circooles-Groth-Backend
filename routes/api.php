<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\CategoryController;
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


// Admin Controller
Route::group(['prefix' => 'admin', 'middleware' => ['jwt.auth', 'admin']], function () {

    Route::controller(AdminController::class)->group(function () {
        Route::get('/subject', 'getSubject');
        Route::post('/subject/store', 'storeSubject');
        Route::delete('/subject/destroy/{id}', 'destroySubject');
    });
    Route::prefix('category')->controller(CategoryController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/store', 'store');
        Route::get('/show/{slug}', 'show');
        Route::put('/update/{slug}', 'update');
        Route::delete('/destroy/{slug}', 'destroy');
    });
    Route::prefix('sub-category')->controller(CategoryController::class)->group(function () {
        Route::get('/','indexSubCategory');
        Route::post('/store', 'storeSubCategory');
        Route::get('/show-by-category/{category_id}', 'showSubCategoryByCategoryId');
        Route::put('/update/{slug}', 'subCategoryUpdate');
        Route::delete('/destroy/{slug}', 'destroySubCategory');
    });
});
