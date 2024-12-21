<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
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
}
