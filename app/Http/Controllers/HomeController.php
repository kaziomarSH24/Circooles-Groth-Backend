<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\TutorInfo;
use Illuminate\Http\Request;

class HomeController extends Controller
{

    //search course by title & tutor name
    public function search(Request $request)
    {
        $searchTerm = $request->input('search', '');
        $categoryId = $request->input('category_id', null);
        $type = $request->input('type', 'course');
        $subject_id = $request->input('subject_id', null);

        if ($type === 'tutor') {
            $tutors = TutorInfo::with('user');
            if($searchTerm) {
                $tutors->whereHas('user', function ($query) use ($searchTerm) {
                    $query->where('name', 'like', '%' . $searchTerm . '%');
                });
            }
            if($subject_id){
                $tutors->whereJsonContains('subjects_id', (string)$subject_id);
            }

            $tutors = $tutors->paginate($request->per_page ?? 6);

            $tutors->getCollection()->transform(function ($tutor) {
                return [
                    'id' => $tutor->id,
                    'name' => $tutor->user->name,
                    'subjects' => $tutor->subjects->pluck('name')->toArray(),
                    'avatar' => $tutor->user->avatar,
                    'expertise_area' => $tutor->expertise_area,
                    'language' => $tutor->language,
                    'session_charge' => $tutor->session_charge,
                ];
            });

            return response()->json([
                'success' => true,
                'tutors' => $tutors,
            ]);
        } else {
            $courses = Course::with('category', 'reviews')
                ->where('title', 'like', '%' . $searchTerm . '%');
            if ($categoryId) {
                $courses->where('category_id', $categoryId);
            }
            $courses = $courses->paginate($request->per_page ?? 6);
            $courses->getCollection()->transform(function ($course) {
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'thumbnail' => $course->thumbnail,
                    'category' => $course->category->name,
                    'category_slug' => $course->category->slug,
                    'category_id' => $course->category->id,
                    'duration' => $course->duration,
                    'language' => $course->language,
                    'price' => $course->price,
                    'rating' => $course->reviews->avg('rating'),
                    'total_reviews' => $course->reviews->count(),
                ];
            });
            return response()->json([
                'success' => true,
                'courses' => $courses,
            ]);
        }
    }

















    public function allCategory()
    {
        $categories = Category::all();

        $categories->transform(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ];
        });
        if ($categories->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    public function allCourses(Request $request)
    {
        $courses = Course::with('category', 'reviews')
            ->when($request->category_id, function ($query) use ($request) {
                return $query->where('category_id', $request->category_id);
            })
            ->paginate($request->per_page ?? 6);

        $courses->getCollection()->transform(function ($course) {
            return [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'thumbnail' => $course->thumbnail,
                'category' => $course->category->name,
                'category_slug' => $course->category->slug,
                'category_id' => $course->category->id,
                'duration' => $course->duration,
                'language' => $course->language,
                'price' => $course->price,
                'rating' => $course->reviews->avg('rating'),
                'total_reviews' => $course->reviews->count(),
            ];
        });


        if ($courses->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'courses' => $courses,
        ]);
    }

    //all categories with courses
    public function allCategoriesWithCourses(Request $request)
    {
        $categories = Category::with('courses')
            ->when($request->category_id, function ($query) use ($request) {
                return $query->where('id', $request->category_id);
            })
            ->paginate($request->per_page ?? 6);

        $categories->getCollection()->transform(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'total_courses' => $category->courses->count(),
                'courses' => $category->courses->transform(function ($course) {
                    return [
                        'id' => $course->id,
                        'title' => $course->title,
                        'slug' => $course->slug,
                        'thumbnail' => $course->thumbnail,
                        'duration' => $course->duration,
                        'language' => $course->language,
                        'price' => $course->price,
                        'rating' => $course->reviews->avg('rating'),
                        'total_reviews' => $course->reviews->count(),
                    ];
                }),
            ];
        });

        if ($categories->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    //top rated tutor
    public function topRatedTutors(Request $request)
    {
        $tutors = TutorInfo::with('tutorReviews', 'user')
            ->withAvg('tutorReviews as avg_rating', 'rating')
            ->orderByDesc('avg_rating')
            ->paginate($request->per_page ?? 6);

        $tutors->getCollection()->transform(function ($tutor) {
            return [
                'id' => $tutor->id,
                'name' => $tutor->user->name,
                'avatar' => $tutor->user->avatar,
                'expertise_area' => $tutor->expertise_area,
                'language' => $tutor->language,
                'session_charge' => $tutor->session_charge,
                'subjects' => $tutor->subjects->pluck('name')->toArray(),
                'avg_rating' => round($tutor->avg_rating, 1),
                'total_reviews' => $tutor->tutorReviews->count(),
            ];
        });

        if ($tutors->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tutor not found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'tutors' => $tutors,
        ]);
    }
    //top rated tutor
    public function tutorService(Request $request)
    {
        $serviceType = $request->input('service_type', 'all');

        $query = TutorInfo::with('tutorReviews', 'user')
            ->withAvg('tutorReviews as avg_rating', 'rating');
        // ->orderByDesc('avg_rating');
        if ($serviceType === 'online') {
            $query->where('online', '!=', null);
        } elseif ($serviceType === 'offline') {
            $query->where('offline', '!=', null);
        } elseif ($serviceType === 'all') {
            $query->whereNotNull('online')->orWhereNotNull('offline');
        }
        $tutors = $query->orderByDesc('id')
            ->paginate($request->per_page ?? 6);

        $tutors->getCollection()->transform(function ($tutor) use ($serviceType) {
            return [
                'id' => $tutor->id,
                'name' => $tutor->user->name,
                'avatar' => $tutor->user->avatar,
                'expertise_area' => $tutor->expertise_area,
                'language' => $tutor->language,
                'session_charge' => $tutor->session_charge,
                'subjects' => $tutor->subjects->pluck('name')->toArray(),
                ...($serviceType === 'all'
                    ? [
                        'online' => json_decode($tutor->online, true),
                        'offline' => json_decode($tutor->offline, true),
                    ]
                    : [
                        ($serviceType === 'online' ? 'online' : 'offline') => json_decode($serviceType === 'online' ? $tutor->online : $tutor->offline, true),
                    ]
                ),
                // 'offline' => json_decode($tutor->offline, true),
                'avg_rating' => round($tutor->avg_rating, 1),
                'total_reviews' => $tutor->tutorReviews->count(),
            ];
        });

        if ($tutors->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tutor not found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'tutors' => $tutors,
        ]);
    }
}
