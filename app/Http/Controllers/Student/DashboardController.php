<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
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


    //upcomming sessions
    public function upcomingSessions(Request $request)
    {
        $perPage = $request->per_page;
        $sessionss = auth()->user()->tutorBookings()->where('status', 'enrolled')->get();
        if ($sessionss->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No session found'
            ]);
        }
        $sessions = $sessionss->transform(function ($session) {
            $schedules = collect(json_decode($session->schedule, true));
            return [
                'id' => $session->id,
                'tutor_id' => $session->tutor_id,
                'tutor_name' => $session->tutor->user->name,
                'schedule' => $schedules,
            ];
        });
        $today = Carbon::today()->toDateString();
        $upcoming = [];
        foreach ($sessions as $session) {
            foreach ($session['schedule'] as $schedule) {
                $upcoming[] = [
                    'tutor' => $session['tutor_name'],
                    'date' => $schedule['date'],
                    'day' => $schedule['day'],
                    'time' => $schedule['time'],
                    'status' => $schedule['date'] >= $today ? 'upcoming' : 'past'
                ];
            }
        }

        $upcomingCollection = collect($upcoming)->sortByDesc('date');
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $upcomingCollection->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $paginator = new LengthAwarePaginator(
            $currentItems,
            $upcomingCollection->count(),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );

        return response()->json([
            'success' => true,
            'sessions' => $paginator->items(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'first_page_url' => $paginator->url(1),
                'last_page_url' => $paginator->url($paginator->lastPage()),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'path' => $paginator->resolveCurrentPath(),
            ],
        ]);
    }
}
