<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\TutorVerification;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /*===========
    Subject Controller
    =============*/
    //get subject
    public function getSubject()
    {
        $subjects = Subject::all();
        if ($subjects->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'subjects' => $subjects,
        ]);
    }
    //store subject
    public function storeSubject(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);

            $subject = new Subject();
            $subject->name = $request->name;
            $subject->description = $request->description;
            $subject->save();

            return response()->json([
                'success' => true,
                'message' => 'Subject created successfully',
                'subject' => $subject,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    //destroy subject
    public function destroySubject($id)
    {
        $subject = Subject::find($id);
        if (!$subject) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found',
            ], 404);
        }
        $subject->delete();
        return response()->json([
            'success' => true,
            'message' => 'Subject deleted successfully',
        ]);
    }

    /*====================
    Subject Controller end
    =====================*/

    /*=============
    User Section
    =============*/
    public function allUsers(Request $request)
    {
        $users = User::when(request('search'), function ($query) {
            return $query->where('name', 'like', '%' . request('search') . '%')
                ->orWhere('email', 'like', '%' . request('search') . '%');
        })->paginate($request->per_page ?? 10);

        if ($users->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $users->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar,
            ];
        });

        return response()->json([
            'success' => true,
            'total_users' => User::count(),
            'users' => $users,
        ]);
    }

    //destroy user
    public function destroyUser($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }
        $user->delete();
        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /*====================
    User Section end
    =====================*/

    /*=============
    Tutor verify Section
    =============*/

    //all tutor verification info
    public function allTutorVerificationInfo(Request $request)
    {
        $verifyTutors = TutorVerification::with('tutor.user')
            ->when(request('search'), function ($query) {
                return $query->whereHas('tutor.user', function ($query) {
                    $query->where('name', 'like', '%' . request('search') . '%')
                        ->orWhere('email', 'like', '%' . request('search') . '%');
                });
            })->paginate($request->per_page ?? 10);

        if ($verifyTutors->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tutor verification info not found',
            ], 404);
        }

        $verifyTutors->getCollection()->transform(function ($verifyTutor) {
            return [
                'id' => $verifyTutor->id,
                'name' => $verifyTutor->tutor->user->name,
                'email' => $verifyTutor->tutor->user->email,
                'payment_status' => $verifyTutor->payment_status,
                'status' => $verifyTutor->status,
            ];
        });

        return response()->json([
            'success' => true,
            'tutor_infos' => $verifyTutors,
        ]);
    }

    //get tutor verification info
    public function getTutorVerificationInfo($id)
    {
        $verifyTutor = TutorVerification::with('tutor.user')->find($id);
        if (!$verifyTutor) {
            return response()->json([
                'success' => false,
                'message' => 'Tutor verification info not found',
            ], 404);
        }
        $verifyTutor = [
            'academic_certificates' => json_decode($verifyTutor->academic_certificates),
            'id_card' => json_decode($verifyTutor->id_card),
            'tsc' => json_decode($verifyTutor->tsc),
        ];
        return response()->json([
            'success' => true,
            'tutor_info' => $verifyTutor,
        ]);
    }

    //verify status update
    public function updateTutorVerifyStatus(Request $request, $id)
    {
        $verifyTutor = TutorVerification::find($id);
        if (!$verifyTutor) {
            return response()->json([
                'success' => false,
                'message' => 'Tutor verification info not found',
            ], 404);
        } elseif ($verifyTutor->status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Tutor already verified',
            ], 400);
        }
        $verifyTutor->status = $request->status ?? $verifyTutor->status;
        $verifyTutor->save();
        return response()->json([
            'success' => true,
            'message' => 'Tutor verification status updated successfully',
        ]);
    }

    //destroy tutor verification info
    public function verifyTutorInfo($id)
    {
        $verifyTutor = TutorVerification::find($id);
        if (!$verifyTutor) {
            return response()->json([
                'success' => false,
                'message' => 'Tutor verification info not found',
            ], 404);
        }
        $verifyTutor->delete();
        return response()->json([
            'success' => true,
            'message' => 'Tutor verification info deleted successfully',
        ]);
    }

    /*====================
    Tutor verify Section end
    =====================*/


    /*=============
    Transaction Section
    =============*/

    //all transactions
    public function transactions(Request $request)
    {

        $transactions = Transaction::with('buyer')
            ->paginate($request->per_page ?? 10);
        if ($transactions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        $transactions->getCollection()->transform(function ($transaction) {
            return [
                'id' => $transaction->id,
                'name' => $transaction->buyer->name,
                'email' => $transaction->buyer->email,
                'amount' => $transaction->amount,
                'status' => $transaction->status === 'success' ? 'paid' : 'pending',
                'date' => $transaction->created_at->format('M d, Y'),
            ];
        });

        return response()->json([
            'success' => true,
            'transactions' => $transactions,
        ]);
    }
}
