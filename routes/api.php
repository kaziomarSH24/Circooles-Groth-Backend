<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\PaystackTransferController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Student\CourseBookingController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\ReviewController;
use App\Http\Controllers\Tutor\DashboardController as TutorDashboardController;
use App\Http\Controllers\Student\StudentController;
use App\Http\Controllers\Tutor\TutorAccountDetails;
use App\Http\Controllers\Tutor\TutorController;
use App\Jobs\SendSessionReminder;
use App\Models\Schedule;
use App\Services\PaystackService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use PgSql\Lob;
use SebastianBergmann\CodeCoverage\Report\Html\Dashboard;

//Home Controller
Route::controller(HomeController::class)->group(function () {
    Route::get('/categories', 'allCategory');
});







// Auth Controller
Route::controller(AuthController::class)->group(function () {
    Route::post('/login', 'login');
    Route::post('/register', 'register');
    Route::get('/logout', 'logout')->middleware('jwt.auth');
    Route::get('/verify-email', 'verifyEmail');
    Route::get('/resentOtp', 'resendOtp');
    Route::post('reset-password', 'resetPassword');

    //social login
    Route::get('/auth/google', 'loginWithGoogle')
        ->name('google.login');

    //social login callback
    Route::get('/auth/google/callback', [AuthController::class, 'googleLoginCallback'])->name('google.login.callback');

    //social login callback
    Route::get('/auth/google/callback', 'googleLoginCallback')
        ->name('google.login.callback');

    Route::middleware('jwt.auth')->group(function () {
        Route::get('/user', 'getUser');
        Route::put('/update-profile', 'updateProfile');
        Route::put('/update-password', 'updatePassword');
    });
});



// Tutor Controller
Route::group(['prefix' => 'tutor', 'middleware' => ['jwt.auth', 'tutor']], function () {
    Route::controller(TutorController::class)->group(function () {
        Route::get('/', 'getTutor');
        Route::put('/update-profile', 'updateTutorProfile');
        Route::get('/upcoming-session', 'upcomingSessions');
        Route::put('/update-link', 'updateLink');
        Route::get('/reschedule-session/{schedule_id}', 'rescheduleSession');
        Route::post('/verify-tutor-info', 'verifyTutorInfo');
        Route::get('/verify-tutor-info', 'getTutorVerificationInfo');


        //tutor verify transaction callback
        Route::get('/verify/callback', 'tutorVerifyCallback')
            ->name('tutor.verify.callback')
            ->withoutMiddleware(['jwt.auth', 'tutor']);
        //check method
        Route::get('/check-method', 'checkMethod');
    });
    Route::prefix('account')->controller(TutorAccountDetails::class)->group(function () {
        Route::post('/create/recipient', 'tutorRecipientAccount');
        Route::get('/list/recipient', 'listRecipient');
        Route::get('/fetch/recipient', 'fetchRecipient');
        Route::put('/update/recipient', 'updateRecipient');
        Route::delete('/delete/recipient', 'deleteRecipient');
    });

    //tutor Dashboard
    Route::controller(TutorDashboardController::class)->group(function () {
        Route::get('/enrolled-courses', 'enrolledCourses');
        Route::get('/completed-courses', 'completedCourses');
        Route::get('/total-students', 'totalStudents');
        Route::get('/total-earnings', 'totalEarnings');
        Route::get('/total-earning-graph', 'totalEarningsGraph');
        Route::get('/total-review-graph', 'totalReviewsGraph');
        Route::get('/review-summary', 'reviewSummary');
    });
});






// Admin Routes
Route::group(['prefix' => 'admin', 'middleware' => ['jwt.auth', 'admin']], function () {

    //admin controller
    Route::controller(AdminController::class)->group(function () {
        Route::get('/subject', 'getSubject');
        Route::post('/subject/store', 'storeSubject');
        Route::delete('/subject/destroy/{id}', 'destroySubject');

        //all users
        Route::prefix('users')->group(function () {
            Route::get('/all', 'allUsers');
            Route::delete('/destroy/{id}', 'destroyUser');
        });

        //tutor verification info
        Route::prefix('tutor')->group(function () {
            Route::get('/verify-info', 'allTutorVerificationInfo');
            Route::get('/verify-info/{id}', 'getTutorVerificationInfo');
            Route::put('/verify-status/{id}', 'updateTutorVerifyStatus');
            Route::delete('/verify-info/{id}', 'verifyTutorInfo');
        });

        //transactions history
        Route::prefix('transactions')->group(function () {
            Route::get('/', 'transactions');
        });
    });

    //admin category controller
    Route::prefix('category')->controller(CategoryController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/store', 'store');
        Route::get('/show/{slug}', 'show');
        Route::put('/update/{slug}', 'update');
        Route::delete('/destroy/{slug}', 'destroy');
    });
    Route::prefix('sub-category')->controller(CategoryController::class)->group(function () {
        Route::get('/', 'indexSubCategory');
        Route::post('/store', 'storeSubCategory');
        Route::get('/show-by-category/{category_id}', 'showSubCategoryByCategoryId');
        Route::put('/update/{slug}', 'subCategoryUpdate');
        Route::delete('/destroy/{slug}', 'destroySubCategory');
    });

    Route::prefix('course')->controller(CourseController::class)->group(function () {
        Route::get('/', 'indexCourse')->withoutMiddleware(['jwt.auth', 'admin']);
        Route::post('/store', 'storeCourse');
        Route::get('/show/{id}', 'showCourse')->withoutMiddleware(['jwt.auth', 'admin']);
        Route::put('/update/{id}', 'updateCourse');
        Route::delete('/destroy/{id}', 'destroyCourse');

        //currictulum routes
        Route::get('/curriculum/{course_id}', 'getCurriculum');
        Route::post('/curriculum/store/{course_id}', 'storeCurriculum');
        Route::put('/curriculum/update/{id}', 'updateCurriculum');
        Route::delete('/curriculum/destroy/{id}', 'destroyCurriculum');

        //lesson routes
        Route::get('/lecture/{curriculum_id}', 'getLecture');
        Route::post('/lecture/store/{curriculum_id}', 'storeLecture');
        Route::put('/lecture/update/{id}', 'updateLecture');
        Route::delete('/lecture/destroy/{id}', 'destroyLecture');
    });
});


// Student Controller
Route::group(['prefix' => 'student', 'middleware' => 'jwt.auth'], function () {
    Route::controller(StudentController::class)->group(function () {
        Route::get('/all-tutors', 'allTutors');
        Route::get('/tutor-expertise-area', 'findTutorByExpertiseArea');
        Route::get('/tutor/profile/{id}', 'tutorProfile');

        //booking routes
        Route::post('/book-tutor', 'bookTutor');
        //refund booking routes
        Route::post('/refund-booking/{booking_id}', 'refundAmount');

        //booking callback
        Route::get('/book-tutor/callback', 'bookingCallback')
            ->name('tutor.booking.callback')->withoutMiddleware('jwt.auth');
    });

    //course booking routes
    Route::controller(CourseBookingController::class)->group(function () {
        Route::post('/course-booking', 'courseBooking');

        //course payment callback
        Route::get('/course-payment/callback', 'coursePaymentCallback')
            ->name('course.payment.callback')->withoutMiddleware('jwt.auth');
    });

    //dashboard routes
    Route::controller(DashboardController::class)->group(function () {
        Route::get('/dashboard', 'dashboard');
        Route::get('enrolled-courses', 'enrolledCourses');
        Route::get('my-tutor', 'myTutor');
        Route::get('upcoming-session', 'upcomingSessions');
    });

    //review routes
    Route::controller(ReviewController::class)->group(function () {
        Route::post('/give-tutor-review/{tutor_id}', 'giveTutorReview');
        Route::delete('/delete-tutor-review/{review_id}', 'deleteTutorReview');

        //course review
        Route::post('/give-course-review/{course_id}', 'giveCourseReview');
        Route::delete('/delete-course-review/{review_id}', 'deleteCourseReview');
    });
});

//transfer routes

//paymetn verify
Route::get('/payment/verify', function (Request $request) {
    $paystack = new PaystackService();
    $response = $paystack->verifyTransaction($request->reference);
    return response()->json(['response' => $response]);
});

Route::prefix('transfer')->controller(PaystackTransferController::class)->group(function () {
    Route::post('/test', 'transfer');
    Route::post('/transferToTutor', 'transferToTutor');
});

//test route
Route::get('/test', function () {
    $sessions = Schedule::where('is_started', false)
    ->where('start_time', '>', now())
    ->where('start_time', '<', now()->addHours(24))
    ->get();

    $sessions->transform(function ($session) {
        return [
            'id' => $session->id,
            'tutor_name' => $session->tutorBooking->tutorInfo->tutor->name,
            'tutor_email' => $session->tutorBooking->tutorInfo->tutor->email,
            'student_name' => $session->tutorBooking->student->name,
            'student_email' => $session->tutorBooking->student->email,
            'start_time' => $session->start_time,
            'duration' => Carbon::parse($session->start_time)->diffInHours(Carbon::parse($session->end_time)) . ' hours',
            'type' => $session->type,
            'zoom_link' => $session->zoom_link,
        ];
    });

    foreach($sessions as $session) {
        $minutesDiff = Carbon::parse($session['start_time'])->diff(now());
        dd($minutesDiff);
      dd( Carbon::parse($session['start_time'])->diffInMinutes(now()));

}


});
