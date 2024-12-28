<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\TutorBooking;
use App\Models\TutorReview;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    //enrolled courses
    public function enrolledCourses(Request $request)
    {
        $perPage = $request->per_page;
        $courses = auth()->user()->checkouts()->paginate($per_page ?? 10);
        if ($courses->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No course found'
            ]);
        }
        $courses->getCollection()->transform(function ($course) {
            return [
                'id' => $course->id,
                'course_id' => $course->course->id,
                'title' => $course->course->title,
                'slug' => $course->course->slug,
                'thumbnail' => $course->course->thumbnail,
                'rating' => $course->course->reviews->avg('rating'),
                'total_reviews' => $course->course->reviews->count(),
                'language' => $course->course->language,
                'duration' => $course->course->duration,
                'price' => $course->course->price,
                'created_at' => $course->created_at,
            ];
        });
        return response()->json([
            'success' => true,
            'courses' => $courses
        ]);
    }

    public function myTutor(Request $request)
    {
        $per_page = $request->per_page;
        $tutors = auth()->user()->tutorBookings()->paginate($per_page ?? 10);
        if ($tutors->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No tutor found'
            ]);
        }
        $tutor = $tutors->unique('tutor_id')->transform(function ($tutor) {
            return [
                'id' => $tutor->tutor->id,
                'name' => $tutor->tutor->user->name,
                'expertise_area' => $tutor->tutor->expertise_area,
                'language' => $tutor->tutor->language,
                'session_charge' => $tutor->tutor->session_charge,
                'avg_rating' => $tutor->tutor->tutorReviews->avg('rating'),
                'total_reviews' => $tutor->tutor->tutorReviews->count(),
            ];
        });

        return response()->json([
            'tutors' => $tutor,
            'pagination' => [
                'total' => $tutors->total(),
                'per_page' => $tutors->perPage(),
                'current_page' => $tutors->currentPage(),
                'last_page' => $tutors->lastPage(),
                'from' => $tutors->firstItem(),
                'to' => $tutors->lastItem(),
                'first_page_url' => $tutors->url(1),
                'last_page_url' => $tutors->url($tutors->lastPage()),
                'next_page_url' => $tutors->nextPageUrl(),
                'prev_page_url' => $tutors->previousPageUrl(),
                'path' => $tutors->resolveCurrentPath(),
                'per_page' => $tutors->perPage(),
                'links' => $tutors->links()
            ]
        ]);
    }

    //upcoming sessions
    public function upcomingSessions(Request $request)
    {
        $perPage = $request->per_page;

        $schedules = Schedule::whereHas('tutorBooking', function ($query) {
            $query->where('student_id', auth()->id())
                ->where('status', 'enrolled');
        })->orderBy('start_time', 'desc')->paginate($perPage ?? 10);

        if ($schedules->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No session found'
            ]);
        }

        $schedules->getCollection()->transform(function ($schedule) {
            return [
                'id' => $schedule->id,
                'tutor_id' => $schedule->tutorBooking->tutor_id,
                'tutor_name' => $schedule->tutorBooking->tutor->user->name,
                'date' => Carbon::parse($schedule->start_time)->format('M d, Y'),
                'day' => Carbon::parse($schedule->start_time)->format('l'),
                'time_slot' => Carbon::parse($schedule->start_time)->format('h:i A') . ' - ' . Carbon::parse($schedule->end_time)->format('h:i A'),
                'type' => $schedule->type,
                'status' => Carbon::parse($schedule->start_time)->isPast() ? 'past' : 'upcoming',
                'zoom_link' => $schedule->zoom_link
            ];
        });

        return response()->json([
            'success' => true,
            'sessions' => $schedules
        ]);
    }

    //reschedule session
   
}
