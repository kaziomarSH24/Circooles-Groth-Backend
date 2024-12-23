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

        $subjects = Subject::whereIn('id', $tutor['subjects_id'])->pluck('name', 'id');

        $reviews = $tutors->tutorReviews->map(function ($review) {
            return [
                'review_by' => $review->user->name,
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

    public function bookTutor(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'tutor_id' => 'required',
                'schedule' => 'required|array',
                'schedule.*.date' => 'required|string',
                'schedule.*.time' => 'required|string',
                'type' => 'required|string',
                'repeat' => 'nullable|string',
                'session_cost' => 'required',
            ]);

            $session_quantity = count($request->schedule);

            $total_cost = $request->session_cost * $session_quantity;
            //booking logic here
            $booking = new TutorBooking();
            $booking->tutor_id = $request->tutor_id;
            $booking->student_id = auth()->id();
            $booking->repeat = $request->repeat ?? null;
            $booking->session_quantity = $session_quantity;
            $booking->session_cost = $request->session_cost;
            $booking->total_cost = $total_cost;
            $booking->save();

            $schedule = $request->input('schedule');

            foreach ($schedule as $value) {
                $times = explode('-', $value['time']);
                $startTime = $value['date'] . ' ' . $times[0];
                $endTime = $value['date'] . ' ' . $times[1];

                $startTimestamp = Carbon::parse($startTime)->format('Y-m-d H:i:s');
                $endTimestamp = Carbon::parse($endTime)->format('Y-m-d H:i:s');

            $schedules = new Schedule();
            $schedules->tutor_booking_id = $booking->id;
            $schedules->start_time = $startTimestamp;
            $schedules->end_time = $endTimestamp;
            $schedules->type = $request->type;
            $schedules->save();
            }

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
                    'booking_type' => 'tutor',
                    'booking_time' => now()
                ]),
                'callback_url' => route('tutor.booking.callback')
            ]);

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
            ]);
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
            $paystact = new PaystackService();
            $response = $paystact->verifyTransaction($request->reference);

            if ($response->status === false) {
                throw new \Exception($response->message);
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
