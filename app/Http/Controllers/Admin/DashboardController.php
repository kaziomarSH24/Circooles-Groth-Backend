<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    //total users, total tutors, total earnings
    public function adminDashboard(Request $request)
    {
        try {
            $filter = $request->input('filter', 'all');
            if ($filter == 'all') {
                $totalUsers = User::count();
                $totalTutors = User::where('role', 'tutor')->count();
                $totalEarnings = Transaction::where('status', 'success')->sum('amount');
            } elseif ($filter == 'weekly') {
                $totalUsers = User::where('created_at', '>=', now()->startOfWeek())
                    ->where('created_at', '<=', now()->endOfWeek())
                    ->count();
                $totalTutors = User::where('role', 'tutor')
                    ->where('created_at', '>=', now()->startOfWeek())
                    ->where('created_at', '<=', now()->endOfWeek())
                    ->count();
                $totalEarnings = Transaction::where('status', 'success')
                    ->where('created_at', '>=', now()->startOfWeek())
                    ->where('created_at', '<=', now()->endOfWeek())
                    ->sum('amount');
            } elseif ($filter == 'monthly') {
                $totalUsers = User::whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->count();
                $totalTutors = User::where('role', 'tutor')
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->count();
                $totalEarnings = Transaction::where('status', 'success')
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->sum('amount');
            } elseif ($filter == 'yearly') {
                $totalUsers = User::whereYear('created_at', now()->year)
                    ->count();
                $totalTutors = User::where('role', 'tutor')
                    ->whereYear('created_at', now()->year)
                    ->count();
                $totalEarnings = Transaction::where('status', 'success')
                    ->whereYear('created_at', now()->year)
                    ->sum('amount');
            }


            return response()->json([
                'success' => true,
                'totalUsers' => $totalUsers,
                'totalTutors' => $totalTutors,
                'totalEarnings' => $totalEarnings,
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
        try {

            $filter = $request->input('filter', 'monthly');

            $totalEarnings = Transaction::selectRaw('sum(amount) as total_earnings,
            MONTH(created_at) as month,
            YEAR(created_at) as year')
                ->where('status', 'success')
                ->groupBy('month', 'year')
                ->get();

            $totalEarnings = $totalEarnings->map(function ($item) {
                $item->month = date('F', mktime(0, 0, 0, $item->month, 10));
                return $item;
            });

            if ($filter == 'monthly') {

                foreach(range(1, 12) as $month) {
                    $monthlyData[] = [
                        'month' => date('F', mktime(0, 0, 0, $month, 10)),
                        'total_earnings' => 0,
                    ];
                }

                foreach ($totalEarnings as $earning) {
                    $index = date('n', strtotime($earning->month)) - 1;
                    $monthlyData[$index]['total_earnings'] = $earning->total_earnings;
                }

                return response()->json([
                    'success' => true,
                    'filter' => $filter,
                    'totalEarnings' => $monthlyData,
                ]);
            }elseif($filter == 'yearly') {
                $yearlyData = $totalEarnings->groupBy('year')->map(function ($yearGroup, $year) {
                    return [
                        'year' => $year,
                        'total_earnings' => $yearGroup->sum('total_earnings'),
                    ];
                })->values();

                return response()->json([
                    'success' => true,
                    'filter' => $filter,
                    'totalEarnings' => $yearlyData,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid filter provided',
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
