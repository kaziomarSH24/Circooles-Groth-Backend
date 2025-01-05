<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Escrow;
use App\Models\Schedule;
use App\Models\TutorBooking;
use App\Models\TutorReview;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    //tutor dashboard stats
    public function tutorDashboardStats(Request $request)
    {
        try {
            $tutorId = Auth::user()->tutorInfo->id;

            $startDate = null;
            $endDate = null;
            $filter = $request->input('filter', null);

            if ($filter === 'weekly') {
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
            } elseif ($filter === 'monthly') {
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
            } elseif ($filter === 'yearly') {
                $startDate = Carbon::now()->startOfYear();
                $endDate = Carbon::now()->endOfYear();
            }

            // Total enrolled courses
            $enrolledCourses = Schedule::whereHas('tutorBooking', function ($query) use ($tutorId) {
                $query->where('tutor_id', $tutorId);
            })->where('status', 'success');

            if ($startDate && $endDate) {
                $enrolledCourses->whereBetween('created_at', [$startDate, $endDate]);
            }
            $totalEnrolled = $enrolledCourses->count();

            // Total completed courses
            $completeCourses = Schedule::whereHas('tutorBooking', function ($query) use ($tutorId) {
                $query->where('tutor_id', $tutorId);
            })->where('status', 'completed');

            if ($startDate && $endDate) {
                $completeCourses->whereBetween('created_at', [$startDate, $endDate]);
            }
            $totalCompleted = $completeCourses->count();

            // Total students
            $totalStudents = TutorBooking::where('tutor_id', $tutorId);
            if ($startDate && $endDate) {
                $totalStudents->whereBetween('created_at', [$startDate, $endDate]);
            }
            $totalStudents = $totalStudents->distinct('student_id')->count('student_id');

            // Total earnings
            $totalEarnings = Escrow::whereHas('booking.schedule', function ($query) {
                $query->where('status', 'completed');
            })->whereHas('booking', function ($query) use ($tutorId) {
                $query->where('tutor_id', $tutorId);
            })->where('status', 'released');

            if ($startDate && $endDate) {
                $totalEarnings->whereBetween('release_date', [$startDate, $endDate]);
            }
            $totalEarnings = $totalEarnings->selectRaw('SUM(hold_amount - deducted_amount) as total')->value('total') ?? 0;

            return response()->json([
                'success' => true,
                'filter' => $filter,
                'data' => [
                    'totalEnrolledCourses' => $totalEnrolled,
                    'totalCompletedCourses' => $totalCompleted,
                    'totalStudents' => $totalStudents,
                    'totalEarnings' => $totalEarnings,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    //total earnings graph
    public function totalEarningsGraph(Request $request)
    {
        $tutorId = Auth::user()->tutorInfo->id;

        $filter = $request->input('filter', 'monthly');

        $totalEarnings = Escrow::whereHas('booking.schedule', function ($query) {
            $query->where('status', 'completed');
        })
            ->whereHas('booking', function ($query) use ($tutorId) {
                $query->where('tutor_id', $tutorId);
            })
            ->where('status', 'released')
            ->selectRaw('
            SUM(hold_amount - deducted_amount) as total,
            MONTH(release_date) as month,
            YEAR(release_date) as year
        ')
            ->groupBy('month', 'year')
            ->get();


        $totalEarnings = $totalEarnings->map(function ($earning) {
            $earning->month_name = date('F', mktime(0, 0, 0, $earning->month, 10));
            return $earning;
        });

        if ($filter === 'monthly') {
            $monthlyData = [];

            foreach (range(1, 12) as $month) {
                $monthlyData[] = [
                    'month_name' => date('F', mktime(0, 0, 0, $month, 10)),
                    'total_earnings' => 0,
                ];
            }

            foreach ($totalEarnings as $earning) {
                $index = $earning->month - 1;
                $monthlyData[$index]['total_earnings'] = $earning->total;
            }

            return response()->json([
                'status' => 'success',
                'filter' => 'monthly',
                'data' => $monthlyData,
            ]);
        } elseif ($filter === 'yearly') {
            $yearlyData = $totalEarnings->groupBy('year')->map(function ($yearGroup, $year) {
                return [
                    'year' => $year,
                    'total_earnings' => $yearGroup->sum('total'),
                ];
            })->values();

            return response()->json([
                'status' => 'success',
                'filter' => 'yearly',
                'data' => $yearlyData,
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Invalid filter provided.',
        ]);
    }

    //total reviews graph monthly and yearly
    public function totalReviewsGraph(Request $request)
    {
        $tutorId = Auth::user()->tutorInfo->id;

        $filter = $request->input('filter', 'monthly');

        $totalReviews = TutorReview::where('tutor_id', $tutorId)
            ->selectRaw('
            COUNT(id) as total,
            MONTH(created_at) as month,
            YEAR(created_at) as year
        ')
            ->groupBy('month', 'year')
            ->get();

        $totalReviews = $totalReviews->map(function ($review) {
            $review->month_name = date('F', mktime(0, 0, 0, $review->month, 10));
            return $review;
        });

        if ($filter === 'monthly') {
            $monthlyData = [];

            foreach (range(1, 12) as $month) {
                $monthlyData[] = [
                    'month_name' => date('F', mktime(0, 0, 0, $month, 10)),
                    'total_reviews' => 0,
                ];
            }

            foreach ($totalReviews as $review) {
                $index = $review->month - 1;
                $monthlyData[$index]['total_reviews'] = $review->total;
            }

            return response()->json([
                'status' => 'success',
                'filter' => 'monthly',
                'data' => $monthlyData,
            ]);
        } elseif ($filter === 'yearly') {
            $yearlyData = $totalReviews->groupBy('year')->map(function ($yearGroup, $year) {
                return [
                    'year' => $year,
                    'total_reviews' => $yearGroup->sum('total'),
                ];
            })->values();

            return response()->json([
                'status' => 'success',
                'filter' => 'yearly',
                'data' => $yearlyData,
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Invalid filter provided.',
        ]);
    }

    //tutor review summary
    public function reviewSummary()
    {
        $tutorId = Auth::user()->tutorInfo->id;

        $totalReviews = TutorReview::where('tutor_id', $tutorId)->count();
        // Group by 'rating' and count
        $ratingSummary = TutorReview::where('tutor_id', $tutorId)
            ->select('rating', \DB::raw('COUNT(*) as count'))
            ->groupBy('rating')
            ->orderBy('rating', 'desc')
            ->get();

        // Calculate percentage for each rating
        $ratingData = $ratingSummary->map(function ($item) use ($totalReviews) {
            return [
                'star' => $item->rating,
                'count' => $item->count,
                'percentage' => $totalReviews > 0 ? round(($item->count / $totalReviews) * 100, 2) : 0,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $ratingData,
            'total_reviews' => $totalReviews,
        ]);
    }
}
