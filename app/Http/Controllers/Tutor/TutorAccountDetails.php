<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\AccountDetails;
use App\Services\PaystackService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TutorAccountDetails extends Controller
{
    public function tutorRecipientAccount(Request $request)
    {
        DB::beginTransaction();
        try{
            $tutor = auth()->user()->tutorInfo;

        $validator = Validator::make($request->all(), [
            'name' => 'nullable',
            'email' => 'nullable', //optional
            'account_number' => 'required',
            'bank_name' => 'nullable',
            'bank_code' => 'required',
            'account_type' => 'nullable',
            'currency' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        //tutor recipient account
        $paystack = new PaystackService();
        $data = [
            'type' => $request->account_type ?? 'nuban',
            'name' => $request->name,
            'email' => $request->email,
            'account_number' => $request->account_number,
            'bank_code' => $request->bank_code,
            'currency' => $request->currency ?? 'ZAR',
        ];

        $recipient = $paystack->createRecipient($data);
        if ($recipient->status === true) {

            $account = AccountDetails::updateOrCreate(
                ['tutor_id' => $tutor->id],
                [
                    'name' => $recipient->data->name,
                    'email' => $recipient->data->email,
                    'account_number' => $recipient->data->details->account_number,
                    'bank_name' => $recipient->data->details->bank_name,
                    'bank_code' => $recipient->data->details->bank_code,
                    'account_type' => $recipient->data->type,
                    'currency' => $recipient->data->currency,
                    'recipient_code' => $recipient->data->recipient_code,
                ]
            );

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Account details saved successfully',
                'recipient' => $recipient,
            ]);
        }else{
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => $recipient->message
            ], 400);
        }

        }catch(\Exception $e){
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }


    //list all recipient
    public function listRecipient(Request $request)
    {
        $paystack = new PaystackService();
        $recipients = $paystack->listRecipient();
        return response()->json([
            'success' => true,
            'recipients' => $recipients,
        ]);
    }


    //fetch recipient
    public function fetchRecipient()
    {
        $tutor = auth()->user()->tutorInfo;
        $recipient_code = $tutor->accountDetails->recipient_code;

        $paystack = new PaystackService();
        $recipient = $paystack->fetchRecipient($recipient_code);
        return response()->json([
            'success' => true,
            'recipient' => $recipient,
        ]);
    }

    //update recipient
    public function updateRecipient(Request $request)
    {
        DB::beginTransaction();
        try{
        $tutor = auth()->user()->tutorInfo;
        $recipient_code = $tutor->accountDetails->recipient_code;
        if (!$recipient_code) {
            return response()->json([
                'success' => false,
                'error' => 'Recipient code not found'
            ], 400);
        }
        $paystack = new PaystackService();
        $recipient = $paystack->updateRecipient($recipient_code, $request->all());

        if ($recipient->status === true) {

            $account = AccountDetails::where('tutor_id', $tutor->id)->update([
                'name' => $request->name,
                'email' => $request->email,
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Recipient updated successfully',
                'recipient' => $recipient,
            ]);
        }else{
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => $recipient->message
            ], 400);
        }

        }catch(\Exception $e){
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    //delete recipient
    public function deleteRecipient(Request $request)
    {
        try{
            $tutor = auth()->user()->tutorInfo;
        $recipient_code = $tutor->accountDetails->recipient_code;
        // dd($recipient_code);
        if (!$recipient_code) {
            return response()->json([
                'success' => false,
                'error' => 'Recipient code not found'
            ], 400);
        }

        $paystack = new PaystackService();
        $recipient = $paystack->deleteRecipient($recipient_code);

        if($recipient->status === true){
            //delete recipient from database
            $account = AccountDetails::find($tutor->accountDetails->id);
            $account->delete();

            return response()->json([
                'success' => true,
                'message' => 'Recipient deleted successfully',
                'recipient' => $recipient,
            ]);
        }else{
            return response()->json([
                'success' => false,
                'error' => $recipient->message
            ], 400);
        }
        }catch(\Exception $e){
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }





    // public function testRecipiant(Request $request)
    // {
    //     $url = "https://api.paystack.co/transferrecipient";

    //     $fields = [
    //         "type" => "nuban",
    //         "name" => "Test User",
    //         "account_number" => 3036139441, // Test account number
    //         "bank_code" => "011",            // GTBank test bank code
    //         "currency" => "NGN"
    //     ];

    //     $fields_string = http_build_query($fields);

    //     // Open connection
    //     $ch = curl_init();

    //     // Set the url, number of POST vars, POST data
    //     curl_setopt($ch, CURLOPT_URL, $url);
    //     curl_setopt($ch, CURLOPT_POST, true);
    //     curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    //     curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    //         "Authorization: Bearer sk_test_1595d78a9189ea591a5e9f64542db9eac011245c",
    //         "Cache-Control: no-cache",
    //     ));

    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Optional for SSL issues

    //     // Execute post
    //     $result = curl_exec($ch);

    //     // Check for cURL errors
    //     if (curl_errno($ch)) {
    //         echo 'Error:' . curl_error($ch);
    //     } else {
    //         echo $result; // Print response
    //     }

    //     curl_close($ch);
    // }
}
