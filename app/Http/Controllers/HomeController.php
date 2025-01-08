<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\TutorInfo;
use Illuminate\Http\Request;

class HomeController extends Controller
{
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
        $courses = Course::with('category','reviews')
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
        $tutors = TutorInfo::with('tutorReviews')
                    ->orderBy('session_charge', 'desc')
                  ->paginate($request->per_page ?? 6);

        $tutors->getCollection()->transform(function ($tutor) {
            return [
                'id' => $tutor->id,
                'name' => $tutor->user->name,
                'expertise_area' => $tutor->expertise_area,
                'language' => $tutor->language,
                'session_charge' => $tutor->session_charge,
                'avg_rating' => round($tutor->tutorReviews->avg('rating'), 1),
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
