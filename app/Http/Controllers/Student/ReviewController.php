<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Review;
use App\Models\TutorBooking;
use App\Models\TutorInfo;
use App\Models\TutorReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    //give review to tutor
    public function giveTutorReview(Request $request, $tutorId)
    {
        $validator = validator($request->all(), [
            // 'tutor_id' => 'required|integer',
            'rating' => 'required|integer|between:1,5',
            'comment' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        //check if student booking any session with this tutor or not
        $isBooked = TutorBooking::where('student_id', Auth::id())
            ->where('tutor_id', $tutorId)
            ->where('status', 'enrolled')
            ->exists();
        // return $isBooked;
        if (!$isBooked) {
            return response()->json([
                'success' => false,
                'message' => 'You have not booked any session with this tutor',
            ], 400);
        }

       //tutor user id and role check
       $tutor = TutorInfo::where('id', $tutorId)->exists();
         if (!$tutor) {
              return response()->json([
                'success' => false,
                'message' => 'Invalid tutor id',
              ], 400);
            }

        $review = new TutorReview();
        $review->user_id = Auth::id();
        $review->tutor_id = $request->tutor_id;
        $review->rating = $request->rating;
        $review->comment = $request->comment;
        $review->save();

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully',
            'review' => $review,
        ]);
    }

    //delete tutor review
    public function deleteTutorReview($reviewId)
    {
        try {
            $review = TutorReview::where('id', $reviewId)->first();
            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found',
                ], 404);
            }

            if ($review->user_id != Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to delete this review',
                ], 403);
            }

            $review->delete();

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }

    //give course review
    public function giveCourseReview(Request $request, $courseId)
    {
        $validator = validator($request->all(), [
            'rating' => 'required|integer|between:1,5',
            'comment' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors(),
            ], 400);
        }

        //check if student enrolled in this course or not
        $isEnrolled = Checkout::where('user_id', Auth::id())
            ->where('course_id', $courseId)
            ->exists();
        if (!$isEnrolled) {
            return response()->json([
                'success' => false,
                'message' => 'You have not enrolled in this course',
            ], 400);
        }

        $review = new Review();
        $review->user_id = Auth::id();
        $review->course_id = $courseId;
        $review->rating = $request->rating;
        $review->comment = $request->comment;
        $review->save();

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully',
            'review' => $review,
        ]);
    }

    //delete course review
    public function deleteCourseReview($reviewId)
    {
        try {
            $review = Review::where('id', $reviewId)->first();
            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found',
                ], 404);
            }

            if ($review->user_id != Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to delete this review',
                ], 403);
            }

            $review->delete();

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }
}
