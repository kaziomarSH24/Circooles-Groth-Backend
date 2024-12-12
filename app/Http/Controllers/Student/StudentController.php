<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\TutorInfo;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    //fetch all tutors
     public function allTutors(Request $request){
        $per_page = $request->per_page;
        $tutors = TutorInfo::with('user','tutorReviews')
                ->whereNotNull('online')
                ->orWhereNotNull('offline')
                ->paginate($per_page ?? 10);
        $tutors->getCollection()->transform(function($tutor){
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
        public function findTutorByExpertiseArea(Request $request){
            $tutors = TutorInfo::with('user','tutorReviews')
                ->orWhereNotNull('online')
                ->orWhereNotNull('offline')
                ->where('expertise_area', 'like', '%'.$request->expertise_area.'%')
                ->paginate($request->per_page ?? 10);
            $tutors->getCollection()->transform(function($tutor){
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
    public function tutorProfile($id){
        $tutors = TutorInfo::with('user','tutorReviews')
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

        $reviews = $tutors->tutorReviews->map(function($review){
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
            'about'=>$tutor['description'],
            'subjects' => $subjects,
            'total_reviews' => $tutors->tutorReviews->count(),
            'reviews' => $reviews
        ];

        return response()->json(['tutor' => $tutor]);
    }
}
