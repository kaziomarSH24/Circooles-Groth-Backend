<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// use App\Models\TutorInfo;
// use Illuminate\Support\Facades\DB;

// Route::get('/fix-my-data', function () {
//      $tutors = DB::table('tutor_infos')->get();
//     $updatedCount = 0;

//     foreach ($tutors as $tutor) {
//         if (!is_string($tutor->subjects_id) || empty($tutor->subjects_id)) continue;

//         $trimmedData = trim($tutor->subjects_id, '"');
//         $finalCorrectString = stripslashes($trimmedData);

//         DB::table('tutor_infos')
//             ->where('id', $tutor->id)
//             ->update(['subjects_id' => $finalCorrectString]);

//         $updatedCount++;
//     }
//     return "Final cleanup complete. Total records updated: " . $updatedCount;
// });


