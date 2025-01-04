<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Escrow;
use App\Models\Schedule;
use App\Models\TutorBooking;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

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

    //total earnings show all monthly wise
    public function totalEarningsMonthly(Request $request)
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
    }

    elseif ($filter === 'yearly') {
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




}
