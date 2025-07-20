<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Escrow;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\TutorBooking;
use App\Models\TutorInfo;
use App\Models\TutorVerification;
use App\Models\User;
use App\Models\Wallets;
use App\Services\PaystackService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class TutorController extends Controller
{

    public function getTutor(Request $request)
    {
        try {
            $user = Auth::user();
            $tutor = TutorInfo::with('user', 'tutorVerification')->where('user_id', $user->id)->first();
            // return response()->json([
            //     'success' => true,
            //     'data' => $tutor,
            // ]);
            if (!$tutor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tutor not found',
                    'data' => $user,
                ]);
            }

            $tutor = collect($tutor)->transform(function ($value, $key) {
                if ($key == 'subjects_id') {
                     return is_string($value) ? json_decode($value) : $value;
                }
                if ($key == 'online') {
                    return json_decode($value);
                }
                if ($key == 'offline') {
                    return json_decode($value);
                }
                return $value;
            });
            // return response()->json([
            //     'success' => true,
            //     'data' => $tutor,
            // ]);
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
                'status' => $tutor['tutor_verification']['status'] ?? 'pending',
                'created_at' => $tutor['created_at'],
            ];
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updateTutorProfile(Request $request)
    {
        $subject_id = $request->input('subjects_id');
        $subjectsArray = [];
        if (is_string($subject_id) && !empty($subject_id)) {

            $subjectsArray = array_map('trim', explode(',', $subject_id));
        }
        $request->merge(['subjects_id' => $subjectsArray]);
        $validetor = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
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

        $avatar = $request->file('avatar');
        if ($avatar) {
            if ($user->avatar) {
                $old_avatar = str_replace('/storage/', '', parse_url($user->avatar)['path']);
                // dd($old_avatar);
                $avatarPath = public_path('avatars/' . $old_avatar);
                // dd($avatarPath);
                if (file_exists($avatarPath)) {
                    unlink($avatarPath); // delete old image
                }
            }

            $avatar_name = time() . '.' . $avatar->extension();
            if (!file_exists(public_path('avatars'))) {
                mkdir(public_path('avatars'), 0777, true); //create directory if not exists
            }
            $avatar->move(public_path('avatars'), $avatar_name);
            $user->avatar = $avatar_name;
            $user->save();
        }

        $tutor = TutorInfo::UpdateOrCreate(
            ['user_id' => $user->id],
            [
                'address' => $request->address,
                'description' => $request->description,
                'subjects_id' => $subjectsArray,
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
                'academic_certificates.*.image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048|',
                'id_card' => 'required|array',
                'id_card.type' => 'required|string',
                'id_card.front_side' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048|',
                'id_card.back_side' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048|',
                'tsc' => 'nullable|array',
                'tsc.number' => 'required|string',
                'tsc.image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048|',
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

                //initialize payment
                $paystack = new PaystackService();
                $response = $paystack->initializeTransaction([
                    'amount' => $request->verification_fee * 100,
                    'email' => Auth::user()->email,
                    'reference' => referenceId(),
                    'callback_url' => $request->redirect_url ?: route('tutor.verify.callback'),
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
        if (is_array($academic_certificates)) {
            foreach ($academic_certificates as $certificate) {
                if (isset($certificate->image)) {
                    $certificate->image = asset('uploads/tutor/academic_certificates/' . $certificate->image);
                }
            }
        }


        $idCard = json_decode($tutor_verification->id_card);
        if (is_object($idCard)) {
            if (isset($idCard->front_side)) {
                $idCard->front_side = asset('uploads/tutor/id_card/' . $idCard->front_side);
            }
            if (isset($idCard->back_side)) {
                $idCard->back_side = asset('uploads/tutor/id_card/' . $idCard->back_side);
            }
        }

        $tsc = json_decode($tutor_verification->tsc);
        if (is_object($tsc) && isset($tsc->image)) {
            $tsc->image = asset('uploads/tutor/tsc/' . $tsc->image);
        }

        $data = [
            'id' => $tutor_verification->id,
            'tutor_id' => $tutor_verification->tutor_id,
            'academic_certificates' => $academic_certificates,
            'id_card' => $idCard,
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
                if ($transaction->status === 'success') {
                    return response()->json([
                        'success' => true,
                        'message' => 'Payment already verified',
                    ]);
                }
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
                    if (!$balance) {
                        $balance = 0;
                    }
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



    //get tutor upcoming sessions
    public function upcomingSessions(Request $request)
    {
        $perPage = $request->per_page;
        $tutor_id = Auth::user()->tutorInfo->id;
        $schedules = Schedule::whereHas('tutorBooking', function ($query) use ($tutor_id) {
            $query->where('tutor_id', $tutor_id)
                ->where('status', 'enrolled');
        })->orderBy('start_time', 'desc')->paginate($perPage ?? 10);
        if ($schedules->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No upcoming session found',
            ]);
        }

        $schedules->getCollection()->transform(function ($schedule) {
            return [
                'id' => $schedule->id,
                'student_id' => $schedule->tutorBooking->student_id,
                'student_name' => $schedule->tutorBooking->student->name,
                'email' => $schedule->tutorBooking->student->email,
                'date' => Carbon::parse($schedule->start_time)->format('M d, Y'),
                'day' => Carbon::parse($schedule->start_time)->format('l'),
                'time_slot' => Carbon::parse($schedule->start_time)->format('h:i A') . ' - ' . Carbon::parse($schedule->end_time)->format('h:i A'),
                'type' => $schedule->type,
                'status' => Carbon::parse($schedule->start_time)->isPast() ? 'past' : 'upcoming',
                'zoom_link' => $schedule->zoom_link
            ];
        });

        return response()->json([
            'success' => true,
            'session' => $schedules,
        ]);
    }


    public function checkMethod()
    {
        $admin_id = User::where('role', 'admin')->first()->id;

        return response()->json([
            'success' => true,
            'data' => $admin_id,
        ]);
    }

    //update tutor session link
    public function updateLink(Request $request)
    {
        $id = $request->id;
        $zoom_link = $request->zoom_link;
        $tutor_id = Auth::user()->tutorInfo->id;

        $schedule = Schedule::find($id);
        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found',
            ]);
        }
        //check authourization
        if ($schedule->tutorBooking->tutor_id != $tutor_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ]);
        }
        $schedule->zoom_link = $zoom_link;
        $schedule->save();

        return response()->json([
            'success' => true,
            'message' => 'Link updated successfully',
        ]);
    }

    //reschedule session
    public function rescheduleSession(Request $request, $schedule_id)
    {
        DB::beginTransaction();
        try {

            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
                'time' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors(),
                ]);
            }

            $id = $schedule_id;
            $times = explode(' - ', $request->time);
            $start_time = $request->date . ' ' . $times[0];
            $end_time = $request->date . ' ' . $times[1];
            $tutor_id = Auth::user()->tutorInfo->id;

            $schedule = Schedule::find($id);
            if (!$schedule) {
                throw new \Exception('Session not found');
            }
            //check session status already rescheduled or not
            if ($schedule->status == 'reschedule') {
                throw new \Exception('Session already rescheduled, you cannot reschedule again');
            }

            //check authourization
            if ($schedule->tutorBooking->tutor_id != $tutor_id) {
                throw new \Exception('Unauthorized');
            }


            //check if session is within 8hrs
            $sessionTime = Carbon::parse($schedule->start_time);
            $currentTime = Carbon::now();
            $diff = $currentTime->diffInMinutes($sessionTime);

            if ($diff < 8 * 60) {
                throw new \Exception('Session cannot be rescheduled within 8hrs');
            }

            //5% penalty charge for rescheduling
            $penalty = $schedule->tutorBooking->session_cost * 0.05;

            // //update wallet
            // $wallet = Wallets::whereHas('user', function ($query) {
            //     $query->where('role', 'admin');
            // })->first();
            // $wallet->balance += $penalty;
            // $wallet->save();

            //update transaction
            $escrow = Escrow::where('booking_id', $schedule->tutor_booking_id)
                ->first();
            $escrow->deducted_amount += $penalty;
            $escrow->save();

            //checking reason json field
            $reason = $schedule->reason ? json_decode($schedule->reason) : [];
            $reason[] = [
                'type' => 'reschedule',
                'detials' => [
                    'reschedule_by' => 'tutor',
                    'reschedule_at' => now(),
                    'previous_date' => $schedule->start_time,
                    'penalty' => $penalty,
                    'reason' => 'Rescheduled by tutor',
                ]
            ];

            $startTimestamp = Carbon::parse($start_time)->format('Y-m-d H:i:s');
            $endTimestamp = Carbon::parse($end_time)->format('Y-m-d H:i:s');

            $schedule->start_time = $startTimestamp;
            $schedule->end_time = $endTimestamp;
            $schedule->reschedule_at = now();
            $schedule->status = 'reschedule';
            $schedule->reason = json_encode($reason);
            $schedule->reschedule_by = 'tutor';
            $schedule->save();

            $data = [
                'email' => $schedule->tutorBooking->student->email,
                'name' => $schedule->tutorBooking->student->name,
                'title' => 'Session Rescheduled',
                'content' => 'Your session has been rescheduled successfully by tutor ' . Auth::user()->name,
                'booking_id' => $schedule->tutor_booking_id,
                'total_cost' => $schedule->tutorBooking->session_cost,
                'session_quantity' => 1,
                'start_date' => $startTimestamp,
                'booking_time' => $schedule->created_at->format('Y-m-d H:i:s'),
            ];
            //send email
            scheduleMail($data);
            $data = [
                'email' => Auth::user()->email,
                'name' => Auth::user()->name,
                'title' => 'Session Rescheduled',
                'content' => 'Your session has been rescheduled successfully. You have been charged a penalty of $' . $penalty,
                'booking_id' => $schedule->tutor_booking_id,
                'total_cost' => $schedule->tutorBooking->session_cost,
                'session_quantity' => 1,
                'start_date' => $startTimestamp,
                'booking_time' => $schedule->created_at->format('Y-m-d H:i:s'),
            ];

            //send email
            scheduleMail($data);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Session rescheduled successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
