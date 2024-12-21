<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Course;
use App\Models\Transaction;
use App\Models\Wallets;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourseBookingController extends Controller
{
    public function courseBooking(Request $request)
    {
        DB::beginTransaction();
        try{
            if (isset($request->course_id)) {
                $course = Course::find($request->course_id);
                if (!$course) {
                   throw new \Exception('Course not found');
                }

                $data = [
                    'amount' => $course->price * 100,
                    'email' => auth()->user()->email,
                    'reference' => referenceId(),
                    'metadata' => [
                        'course_id' => $course->id,
                        'course_title' => $course->title,
                        'course_price' => $course->price,
                        'user_id' => auth()->id(),
                        'user_name' => auth()->user()->name,
                        'user_email' => auth()->user()->email,
                    ],
                    'callback_url' => route('course.payment.callback'),
                ];
                $paystack = new PaystackService();
                $reference = referenceId();
                $response = $paystack->initializeTransaction($data);

                if ($response->status === false) {
                   throw new \Exception($response->message);
                }

                if ($response->status === true) {
                    $transaction = new Transaction();
                    $transaction->seller_id = $course->user_id;
                    $transaction->buyer_id = auth()->id();
                    $transaction->course_id = $course->id;
                    $transaction->amount = $course->price;
                    $transaction->reference = $response->data->reference;
                    $transaction->save();
                    DB::commit();
                    return response()->json([
                        'success' => true,
                        'message' => 'Course payment successful',
                        'payment_url' => $response->data->authorization_url,
                        'reference_id' => $response->data->reference,
                        'data' => $response->data
                    ]);
                }
            }else{
                throw new \Exception('Course id is required');
            }
        }catch (\Exception $e){
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }


    //callback for course payment
    public function coursePaymentCallback(Request $request)
    {
        DB::beginTransaction();
        try{
        $reference = $request->reference;
        $paystack = new PaystackService();
        $response = $paystack->verifyTransaction($reference);

        if ($response->status === false) {
            throw new \Exception($response->message);
        }

        $is_checkout_exist = Checkout::where('reference_id', $reference)->first();
        if ($is_checkout_exist) {
            throw new \Exception('Payment already processed');
        }

            $checkout = new Checkout();
            $checkout->user_id = $response->data->metadata->user_id;
            $checkout->course_id = $response->data->metadata->course_id;
            $checkout->total_amount = $response->data->metadata->course_price;
            $checkout->reference_id = $response->data->reference;
            $checkout->save();

            $transaction = Transaction::where('reference', $reference)->first();
            $transaction->status = 'success';
            $transaction->save();

            $balance = Wallets::where('user_id', $transaction->seller_id)->first()->balance;
            if(!$balance){
                $balance = 0;
            }
            $balance += $transaction->amount;

            Wallets::updateOrCreate(
                ['user_id' => $transaction->seller_id],
                ['balance' => $balance]
            );

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Course payment successful',
                'data' => $response->data
            ]);
        }catch (\Exception $e){
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
