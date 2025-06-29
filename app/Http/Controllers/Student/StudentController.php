<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Escrow;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\TutorBooking;
use App\Models\TutorInfo;
use App\Services\PaystackService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Types\Relations\Car;

class StudentController extends Controller
{
    //fetch all tutors
    public function allTutors(Request $request)
    {
        $per_page = $request->per_page;
        $tutors = TutorInfo::with('user', 'tutorReviews')
            ->whereNotNull('online')
            ->orWhereNotNull('offline')
            ->paginate($per_page ?? 10);
        $tutors->getCollection()->transform(function ($tutor) {
            return [
                'id' => $tutor->id,
                'name' => $tutor->user->name,
                'expertise_area' => $tutor->expertise_area,
                'language' => $tutor->language,
                'session_charge' => $tutor->session_charge,
                'avg_rating' => $tutor->tutorReviews->avg('rating'),
                'total_reviews' => $tutor->tutorReviews->count(),
            ];
        });
        return response()->json(['tutors' => $tutors]);
    }

    //find tutor by expertise area
    public function findTutorByExpertiseArea(Request $request)
    {
        $tutors = TutorInfo::with('user', 'tutorReviews')
            ->orWhereNotNull('online')
            ->orWhereNotNull('offline')
            ->where('expertise_area', 'like', '%' . $request->expertise_area . '%')
            ->paginate($request->per_page ?? 10);
        $tutors->getCollection()->transform(function ($tutor) {
            return [
                'id' => $tutor->id,
                'name' => $tutor->user->name,
                'expertise_area' => $tutor->expertise_area,
                'language' => $tutor->language,
                'session_charge' => $tutor->session_charge,
                'avg_rating' => $tutor->tutorReviews->avg('rating'),
                'total_reviews' => $tutor->tutorReviews->count(),
            ];
        });
        return response()->json(['tutors' => $tutors]);
    }

    //find tutor profile by id
    public function tutorProfile($id)
    {
        $tutors = TutorInfo::with('user', 'tutorReviews')
            ->where('id', $id)
            ->first();
        $tutor = collect($tutors)->transform(function ($value, $key) {
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

        $subjects = Subject::whereIn('id', $tutor['subjects_id'])->pluck('name', 'id');

        $reviews = $tutors->tutorReviews->map(function ($review) {
            return [
                'review_id' => $review->id,
                'review_by' => $review->user->name,
                'avatar' => $review->user->avatar,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at->diffForHumans()
            ];
        });
        $tutor = [
            'id' => $tutor['id'],
            'name' => $tutor['user']['name'],
            'email' => $tutor['user']['email'],
            'phone' => $tutor['user']['phone'],
            'avatar' => $tutor['user']['avatar'],
            'address' => $tutor['address'],
            'expertise_area' => $tutor['expertise_area'],
            'language' => $tutor['language'],
            'session_charge' => $tutor['session_charge'],
            'online' => $tutor['online'],
            'offline' => $tutor['offline'],
            'about' => $tutor['description'],
            'subjects' => $subjects,
            'total_reviews' => $tutors->tutorReviews->count(),
            'reviews' => $reviews
        ];

        return response()->json(['tutor' => $tutor]);
    }

    //tutor average rating
    public function tutorAverageRating($id)
    {
        $tutor = TutorInfo::with('tutorReviews')
            ->where('id', $id)
            ->first();

        if (!$tutor) {
            return response()->json(['message' => 'Tutor not found'], 404);
        }

        $averageRating = $tutor->tutorReviews->avg('rating');
        $totalReviews = $tutor->tutorReviews->count();
        $reviews = $tutor->tutorReviews;

        // Count reviews per star rating (1-5)
        $starCounts = [
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
        ];

        foreach ($tutor->tutorReviews as $review) {
            $rating = (int) $review->rating;
            if (isset($starCounts[$rating])) {
                $starCounts[$rating]++;
            }
        }

        // Paginate reviews (default 10 per page, can be set via ?per_page=)
        $perPage = request()->get('per_page', 10);
        $paginatedReviews = $reviews->map(function ($review) {
            return [
                'review_by' => $review->user->name,
                'avatar' => $review->user->avatar,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at->diffForHumans()
            ];
        })->forPage(request()->get('page', 1), $perPage)->values();

        return response()->json([
            'tutor_id' => $tutor->id,
            'average_rating' => round($averageRating, 1),
            'total_reviews' => $totalReviews,
            'star_counts' => $starCounts,
            'reviews' => $paginatedReviews,
            'current_page' => (int) request()->get('page', 1),
            'per_page' => (int) $perPage,
            'last_page' => ceil($totalReviews / $perPage),
        ]);
    }

    public function bookTutor(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'tutor_id' => 'required|exists:tutor_infos,id',
                'schedule' => 'required|array',
                'schedule.*.date' => 'required|string',
                'schedule.*.time' => 'required|string',
                'type' => 'required|string',
                'repeat' => 'nullable|in:daily,weekly,bi-weekly,monthly',
                'recurrence_end' => $request->repeat ? 'required|date' : 'nullable|date',
                'session_cost' => 'required',
            ]);

            $session_quantity = count($request->schedule);

            $total_cost = $request->session_cost * $session_quantity;
            //booking logic here
            $booking = new TutorBooking();
            $booking->tutor_id = $request->tutor_id;
            $booking->student_id = auth()->id();
            $booking->repeat = $request->repeat ?? null;
            $booking->recurrence_end = $request->recurrence_end ?? null;
            $booking->session_quantity = $session_quantity;
            $booking->session_cost = $request->session_cost;
            $booking->total_cost = $total_cost;
            $booking->save();

            $schedule = $request->input('schedule');

            $allSchedules = [];

            foreach ($schedule as $value) {
                $times = explode('-', $value['time']);
                $startTime = $value['date'] . ' ' . $times[0];
                $endTime = $value['date'] . ' ' . $times[1];

                $startTimestamp = Carbon::parse($startTime);
                $endTimestamp = Carbon::parse($endTime);


                $allSchedules[] = [
                    'tutor_booking_id' => $booking->id,
                    'start_time' => $startTimestamp->format('Y-m-d H:i:s'),
                    'end_time' => $endTimestamp->format('Y-m-d H:i:s'),
                    'type' => $request->type,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $recurrenceEnd = $request->recurrence_end ? Carbon::parse($request->recurrence_end) : null;

                while ($request->repeat && (!$recurrenceEnd || $startTimestamp->lte($recurrenceEnd))) {
                    switch ($request->repeat) {
                        case 'daily':
                            $startTimestamp->addDay();
                            $endTimestamp->addDay();
                            break;
                        case 'weekly':
                            $startTimestamp->addWeek();
                            $endTimestamp->addWeek();
                            break;
                        case 'bi-weekly':
                            $startTimestamp->addWeeks(2);
                            $endTimestamp->addWeeks(2);
                            break;
                        case 'monthly':
                            $startTimestamp->addMonth();
                            $endTimestamp->addMonth();
                            break;
                    }

                    if ($recurrenceEnd && $startTimestamp->gt($recurrenceEnd)) {
                        break;
                    }

                    $allSchedules[] = [
                        'tutor_booking_id' => $booking->id,
                        'start_time' => $startTimestamp->format('Y-m-d H:i:s'),
                        'end_time' => $endTimestamp->format('Y-m-d H:i:s'),
                        'type' => $request->type,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            $session_quantity = count($allSchedules);
            $total_cost = $request->session_cost * $session_quantity;
            $booking->session_quantity = $session_quantity;
            $booking->total_cost = $total_cost;
            $booking->save();
            Schedule::insert($allSchedules);



            //transaction logic here
            $paystact = new PaystackService();
            $response = $paystact->initializeTransaction([
                'amount' => $total_cost * 100,
                'email' => auth()->user()->email,
                'reference' => referenceId(),
                // 'subaccount' => 'ACCT_lbpyasxb68g116g',
                'metadata' => json_encode([
                    'booking_id' => $booking->id,
                    'student_id' => auth()->id(),
                    'student_name' => auth()->user()->name,
                    'student_email' => auth()->user()->email,
                    'tutor_name' => $booking->tutor->user->name,
                    'tutor_email' => $booking->tutor->user->email,
                    'start_date' => $schedule[0]['date'],
                    'booking_type' => 'tutor',
                    'booking_time' => now()
                ]),
                'callback_url' => route('tutor.booking.callback')
            ]);

            //mail data
            $data = [
                'email' => auth()->user()->email,
                'name' => auth()->user()->name,
                'title' => 'Booking Confirmation',
                'content' => 'please click the link below to confirm your booking by making payment' . " <a href='{{$response->data->authorization_url}}'>Click here</a>.",
                'booking_id' => $booking->id,
                'total_cost' => $total_cost,
                'session_quantity' => $session_quantity,
                'start_date' => $schedule[0]['date'],
                'booking_time' => now(),
            ];
            //send mail
            scheduleMail($data);

            if ($response->status === false) {
                throw new \Exception($response->message);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking successful',
                'payment_url' => $response->data->authorization_url,
                'reference_id' => $response->data->reference,
                'data' => $response->data
            ]);
        } catch (\Exception $e) {

            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    //refund booking amount
    public function refundAmount($booking_id)
    {
        DB::beginTransaction();
        try {
            $booking = TutorBooking::find($booking_id);
            $escrow = Escrow::where('booking_id', $booking_id)->first();

            $paystact = new PaystackService();
            $data = [
                'transaction' => $escrow->reference_id,
                'amount' => $escrow->hold_amount * 100,
                // 'currency' => 'ZAR',
                'customer_note' => 'Refund for booking',
                'merchant_note' => 'Refund for booking',
            ];
            $response = $paystact->refund($data);

            if ($response->status === false) {
                throw new \Exception($response->message);
            }

            $booking->status = 'cancel';
            $booking->save();

            $escrow->status = 'refunded';
            $escrow->refund_date = now();
            $escrow->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking refunded successfully',
                'data' => $response->data
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function bookingCallback(Request $request)
    {
        DB::beginTransaction();

        try {

            if (Escrow::where('reference_id', $request->reference)->exists()) {
                throw new \Exception('Transaction already processed');
            }

            $paystact = new PaystackService();
            $response = $paystact->verifyTransaction($request->reference);

            if ($response->status === false) {
                throw new \Exception($response->message);
            }

            if ($response->data->status !== 'success') {
                throw new \Exception('Transaction not successful');
            }


            $booking = TutorBooking::find($response->data->metadata->booking_id);
            $booking->status = 'enrolled';
            $booking->save();

            Schedule::where('tutor_booking_id', $booking->id)->update(['status' => 'success']);

            $escrow = new Escrow();
            $escrow->booking_id = $booking->id;
            $escrow->hold_amount = $booking->total_cost;
            $escrow->status = 'hold';
            $escrow->reference_id = $response->data->reference;
            $escrow->save();

            //mail data
            $data = [
                'email' => $response->data->metadata->student_email,
                'name' => $response->data->metadata->student_name,
                'title' => 'Booking Confirmation',
                'content' => 'Your booking has been confirmed successfully',
                'booking_id' => $booking->id,
                'total_cost' => $booking->total_cost,
                'session_quantity' => $booking->session_quantity,
                'start_date' => $response->data->metadata->start_date,
                'booking_time' => $booking->created_at,
            ];

            //send mail
            scheduleMail($data);

            //send mail to tutor
            $data = [
                'email' => $response->data->metadata->tutor_email,
                'name' => $response->data->metadata->tutor_name,
                'title' => 'Booking Confirmation',
                'content' => 'You have a new booking request',
                'booking_id' => $booking->id,
                'total_cost' => $booking->total_cost,
                'session_quantity' => $booking->session_quantity,
                'start_date' => $response->data->metadata->start_date,
                'booking_time' => $booking->created_at,
            ];
            // send mail
            scheduleMail($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking successful',
                'data' => $response->data
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
