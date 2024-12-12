<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\TutorInfo;
use App\Models\TutorVerification;
use App\Models\User;
use App\Models\Wallets;
use App\Services\PaystackService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class TutorController extends Controller
{

    public function getTutor(Request $request)
    {
        $user = Auth::user();
        $tutor = TutorInfo::with('user')->where('user_id', $user->id)->first();

        if (!$tutor) {
            return response()->json([
                'success' => false,
                'message' => 'Tutor not found',
                'data' => $user,
            ]);
        }

        $tutor = collect($tutor)->transform(function ($value, $key) {
            if ($key == 'subjects_id') {
                return json_decode($value);
            }
            if ($key == 'online') {
                return json_decode($value);
            }
            if ($key == 'offline') {
                return json_decode($value);
            }
            return $value;
        });

        $subjects = Subject::whereIn('id', $tutor['subjects_id'])->get(['id', 'name']);
        $data = [
            'id' => $tutor['id'],
            'user_id' => $tutor['user_id'],
            'name' => $tutor['user']['name'],
            'email' => $tutor['user']['email'],
            'role' => $tutor['user']['role'],
            'edu_level' => $tutor['user']['edu_level'],
            'paystack_customer_id' => $tutor['user']['paystack_customer_id'],
            'phone' => $tutor['user']['phone'],
            'avatar' => $tutor['user']['avatar'],
            'address' => $tutor['address'],
            'description' => $tutor['description'],
            'subjects' => $subjects,
            'designation' => $tutor['designation'],
            'organization' => $tutor['organization'],
            'teaching_experience' => $tutor['teaching_experience'],
            'expertise_area' => $tutor['expertise_area'],
            'degree' => $tutor['degree'],
            'institute' => $tutor['institute'],
            'graduation_year' => $tutor['graduation_year'],
            'time_zone' => $tutor['time_zone'],
            'online' => $tutor['online'],
            'offline' => $tutor['offline'],
            'session_charge' => $tutor['session_charge'],
        ];
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function updateTutorProfile(Request $request)
    {
        $subject_id = $request->input('subjects_id');
        $subject = explode(',', $subject_id);
        $request->merge(['subjects_id' => $subject]);
        $validetor = Validator::make($request->all(), [
            'address' => 'required|string',
            'description' => 'required|string',
            'subjects_id' => 'required|array|min:1|max:3',
            'subjects_id.*' => 'distinct',
            'designation' => 'required|string',
            'organization' => 'required|string',
            'teaching_experience' => 'required|string',
            'expertise_area' => 'required|string',
            'language' => 'nullable|string',
            'degree' => 'required|string',
            'institute' => 'required|string',
            'graduation_year' => 'required|string',
            'time_zone' => 'required|string',
            'online' => 'nullable|json',
            'offline' => 'nullable|json',
            'session_charge' => 'required|string',
        ]);
        if ($validetor->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validetor->errors(),
            ]);
        }


        $user = Auth::user();
        $tutor = TutorInfo::UpdateOrCreate(
            ['user_id' => $user->id],
            [
                'address' => $request->address,
                'description' => $request->description,
                'subjects_id' => json_encode($request->subjects_id),
                'designation' => $request->designation,
                'organization' => $request->organization,
                'teaching_experience' => $request->teaching_experience,
                'expertise_area' => $request->expertise_area,
                'language' => $request->language,
                'degree' => $request->degree,
                'institute' => $request->institute,
                'graduation_year' => $request->graduation_year,
                'time_zone' => $request->time_zone,
                'online' => $request->online,
                'offline' => $request->offline,
                'session_charge' => $request->session_charge,
            ]
        );
        // $tutor->tutorInfo->update($request->all());
        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $tutor,
        ]);
    }

    public function verifyTutorInfo(Request $request)
    {
        DB::beginTransaction();

        try {
            $tutor = TutorInfo::where('user_id', Auth::user()->id)->first();
            if (!$tutor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Set up tutor profile first',
                ]);
            }

            // return request()->all();

            $validator = Validator::make($request->all(), [
                'academic_certificates' => 'required|array',
                'academic_certificates.*.certificate' => 'required|string',
                'academic_certificates.*.image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048|dimensions:max_width=800,max_height=400',
                'id_card' => 'required|array',
                'id_card.type' => 'required|string',
                'id_card.front_side' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048|dimensions:max_width=800,max_height=400',
                'id_card.back_side' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048|dimensions:max_width=800,max_height=400',
                'tsc' => 'nullable|array',
                'tsc.number' => 'required|string',
                'tsc.image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048|dimensions:max_width=800,max_height=400',
                'verification_fee' => 'required|numeric',
                'status' => 'nullable|in:pending,declined,verified',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors(),
                ]);
            }

            //unlink previous images
            $oldCertificates = $tutor->tutorVerification ? json_decode($tutor->tutorVerification->academic_certificates) : null;
            if ($oldCertificates) {
                foreach ($oldCertificates as $key => $certificate) {
                    unlink(public_path('uploads/tutor/academic_certificates/' . $certificate->image));
                }
            }

            $oldIdCard = $tutor->tutorVerification ? json_decode($tutor->tutorVerification->id_card) : null;
            if ($oldIdCard) {
                unlink(public_path('uploads/tutor/id_card/' . $oldIdCard->front_side));
                unlink(public_path('uploads/tutor/id_card/' . $oldIdCard->back_side));
            }

            $oldTcl = $tutor->tutorVerification ? json_decode($tutor->tutorVerification->tsc) : null;
            if ($oldTcl) {
                unlink(public_path('uploads/tutor/tsc/' . $oldTcl->image));
            }

            //certificate image upload
            $certificates = $request->input('academic_certificates');
            foreach ($certificates as $key => $certificate) {

                if ($request->hasFile('academic_certificates.' . $key . '.image')) {
                    if ($request->file('academic_certificates.' . $key . '.image')->isValid()) {
                        $image = $request->file('academic_certificates.' . $key . '.image');
                        $image_name =  time() . rand(100, 999) . '_' . $image->getClientOriginalName();
                        if (!file_exists(public_path('uploads/tutor/academic_certificates'))) {
                            mkdir(public_path('uploads/tutor/academic_certificates'), 0777, true);
                        }
                        $image->move(public_path('uploads/tutor/academic_certificates'), $image_name);
                        $certificates[$key]['image'] = $image_name;
                    }
                }
            }

            //id card image upload
            $id_card = $request->input('id_card');
            if ($request->hasFile('id_card.front_side') && $request->hasFile('id_card.back_side')) {
                if ($request->file('id_card.front_side')->isValid() && $request->file('id_card.back_side')->isValid()) {
                    $front_side = $request->file('id_card.front_side');
                    $back_side = $request->file('id_card.back_side');
                    $front_side_name =  time() . rand(100, 999) . '_' . $front_side->getClientOriginalName();
                    $back_side_name =  time() . rand(100, 999) . '_' . $back_side->getClientOriginalName();
                    if (!file_exists(public_path('uploads/tutor/id_card'))) {
                        mkdir(public_path('uploads/tutor/id_card'), 0777, true);
                    }
                    $front_side->move(public_path('uploads/tutor/id_card'), $front_side_name);
                    $back_side->move(public_path('uploads/tutor/id_card'), $back_side_name);
                    $id_card['front_side'] = $front_side_name;
                    $id_card['back_side'] = $back_side_name;
                }
            }

            //tsc image upload
            $tsc = $request->input('tsc');
            if ($request->hasFile('tsc.image')) {
                if ($request->file('tsc.image')->isValid()) {
                    $tsc_image = $request->file('tsc.image');
                    $tsc_image_name =  time() . rand(100, 999) . '_' . $tsc_image->getClientOriginalName();
                    if (!file_exists(public_path('uploads/tutor/tsc'))) {
                        mkdir(public_path('uploads/tutor/tsc'), 0777, true);
                    }
                    $tsc_image->move(public_path('uploads/tutor/tsc'), $tsc_image_name);
                    $tsc['image'] = $tsc_image_name;
                }
            }

            $verify_payment_status = $tutor->tutorVerification->payment_status ?? null;

            if ($verify_payment_status === null || $verify_payment_status != 'paid') {
                $paystack = new PaystackService();
                $response = $paystack->initializeTransaction([
                    'amount' => $request->verification_fee * 100,
                    'email' => Auth::user()->email,
                    'reference' => referenceId(),
                    'callback_url' => route('tutor.verify.callback'),
                ]);
            }

            if ((isset($response) && $response->status === true) || $verify_payment_status == 'paid') {
                $tutor_verification = TutorVerification::updateOrCreate(
                    ['tutor_id' => $tutor->id],
                    [
                        'academic_certificates' => json_encode($certificates),
                        'id_card' => json_encode($id_card),
                        'tsc' => json_encode($tsc),
                        'verification_fee' => $request->verification_fee,
                        'payment_url' => $response->data->authorization_url ?? null,
                    ]
                );

                if ($verify_payment_status != 'paid') {
                    $transaction = Transaction::create([
                        'seller_id' => User::where('role', 'admin')->first()->id,
                        'buyer_id' => Auth::user()->id,
                        'buyer_type' => 'tutor',
                        'reference' => $response->data->reference,
                        'amount' => $request->verification_fee,
                        'status' => 'pending',
                    ]);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Tutor verification info updated successfully',
                    'reference' => $response->data->reference ?? null,
                    'payment_url' => $response->data->authorization_url ?? null,
                    'data' => $tutor_verification,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment failed',
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Something went wrong',
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }


    //get tutor verification info
    public function getTutorVerificationInfo(Request $request)
    {
        $tutor = TutorInfo::where('user_id', Auth::user()->id)->first();
        if (!$tutor) {
            return response()->json([
                'success' => false,
                'message' => 'Set up tutor profile first',
            ]);
        }

        $tutor_verification = TutorVerification::where('tutor_id', $tutor->id)->first();
        if (!$tutor_verification) {
            return response()->json([
                'success' => false,
                'message' => 'Tutor verification info not found',
            ]);
        }

        $academic_certificates = json_decode($tutor_verification->academic_certificates);
        $id_card = json_decode($tutor_verification->id_card);
        $tsc = json_decode($tutor_verification->tsc);

        $data = [
            'id' => $tutor_verification->id,
            'tutor_id' => $tutor_verification->tutor_id,
            'academic_certificates' => $academic_certificates,
            'id_card' => $id_card,
            'tsc' => $tsc,
            'verification_fee' => $tutor_verification->verification_fee,
            'status' => $tutor_verification->status,
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }



    //verify tutor callback
    public function tutorVerifyCallback(Request $request)
    {
        DB::beginTransaction();
        try {
            $paystack = new PaystackService();
            $reference = $request->query('reference');
            $response = $paystack->verifyTransaction($reference);

            if ($reference) {
                $transaction = Transaction::where('reference', $reference)
                    ->first();
                $tutorId = $transaction->buyer->tutorInfo->id;

                $tutor_verification = TutorVerification::where('tutor_id', $tutorId)
                    ->first();
                if ($response->data->status === 'success') {

                    $tutor_verification->update([
                        'payment_status' => 'paid',
                        'payment_url' => null,
                    ]);

                    $transaction->update([
                        'status' => 'success',
                    ]);

                    $balance = Wallets::where('user_id', $transaction->seller_id)->first()->balance;
                    $balance += $transaction->amount;

                    Wallets::updateOrCreate(
                        ['user_id' => $transaction->seller_id],
                        [
                            'balance' => $balance,
                            'currency' => 'USD',
                        ]
                    );

                    DB::commit();
                    return response()->json([
                        'success' => true,
                        'message' => 'Payment successful',
                        'data' => $tutor_verification,
                    ]);
                } else {
                    $tutor_verification->update([
                        'payment_status' => 'failed',
                        'payment_url' => null,
                    ]);

                    $transaction->update([
                        'status' => 'failed',
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Payment failed',
                        'data' => $tutor_verification,
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid reference',
            ]);
        } catch (\Exception $e) {

            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }


    public function checkMethod()
    {
        $admin_id = User::where('role', 'admin')->first()->id;

        return response()->json([
            'success' => true,
            'data' => $admin_id,
        ]);
    }
}
