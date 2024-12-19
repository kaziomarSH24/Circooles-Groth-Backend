<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Escrow;
use App\Models\TutorInfo;
use App\Services\PaystackService;
use Illuminate\Http\Request;

class PaystackTransferController extends Controller
{
    //transfer to tutor
    // public function transferToTutor(Request $request)
    // {
    //     $tutor = TutorInfo::find($request->tutor_id);
    //     if (!$tutor) {
    //         return response()->json(['error' => 'Tutor not found'], 404);
    //     }

    //     $escrow = Escrow::where('booking_id', $request->booking_id)->first();
    //     if (!$escrow) {
    //         return response()->json(['error' => 'Escrow not found'], 404);
    //     }

    //     $paystack = new PaystackService();
    //     $data = [
    //         'source' => 'balance',
    //         'amount' => $escrow->hold_amount,
    //         'recipient' => $tutor->accountDetails->recipient_code,
    //         'reason' => 'Tutor payment',
    //     ];

    //     $transfer = $paystack->transfer($data);
    //     if ($transfer->status === true) {
    //         $escrow->status = 'released';
    //         $escrow->save();
    //         return response()->json(['message' => 'Transfer successful']);
    //     } else {
    //         return response()->json(['error' => $transfer->message], 400);
    //     }
    // }

    //transfer test
    public function transfer(Request $request)
    {
        $paystack = new PaystackService();
        $data = [
            'source' => 'balance',
            'amount' => $request->amount * 100,
            'recipient' => $request->recipient_code,
            'reason' => 'Test transfer',
        ];

        $transfer = $paystack->transfer($data);
        if ($transfer->status === true) {
            return response()->json([
                'message' => 'Transfer successful',
                'data' => $transfer
            ]);
        } else {
            return response()->json(['error' => $transfer], 400);
        }
    }
}
