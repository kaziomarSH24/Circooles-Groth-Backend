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

class DashboardController extends Controller
{
    // This function is used to display the tutor dashboard
    //count Enrolled Courses and Completed Courses api
    public function enrolledCourses()
    {
        $tutorId = Auth::user()->tutorInfo->id;
        $totalEnrolled = Schedule::whereHas('tutorBooking', function ($query) use ($tutorId) {
            $query->where('tutor_id', $tutorId);
        })->where('status', 'success')->count();

        return response()->json([
            'success' => true,
            'totalEnrolled' => $totalEnrolled,
        ]);
    }

    //total completed courses
    public function completedCourses()
    {
        $tutorId = Auth::user()->tutorInfo->id;
        $totalCompleted = Schedule::whereHas('tutorBooking', function ($query) use ($tutorId) {
            $query->where('tutor_id', $tutorId);
        })->where('status', 'completed')->count();

        return response()->json([
            'success' => true,
            'totalCompleted' => $totalCompleted,
        ]);
    }

    //total students
    public function totalStudents()
    {
        $tutorId = Auth::user()->tutorInfo->id;
        $totalStudents = TutorBooking::where('tutor_id', $tutorId)
            ->distinct('student_id')->count('student_id');

        return response()->json([
            'success' => true,
            'totalStudents' => $totalStudents,
        ]);
    }

    //total earnings
    public function totalEarnings()
    {
        $tutorId = Auth::user()->tutorInfo->id;
        $totalEarnings = Escrow::whereHas('booking.schedule', function ($query) {
            $query->where('status', 'completed');
        })
            ->whereHas('booking', function ($query) use ($tutorId) {
                $query->where('tutor_id', $tutorId);
            })
            ->where('status', 'released')
            ->selectRaw('SUM(hold_amount - deducted_amount) as total')
            ->value('total');

        return response()->json([
            'success' => true,
            'totalEarnings' => $totalEarnings,
        ]);
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
