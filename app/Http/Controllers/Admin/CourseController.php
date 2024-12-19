<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Lecture;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use phpseclib3\Crypt\RC2;

class CourseController extends Controller
{
    /*==============
    Course Section
    ==============*/

    //get course
    public function indexCourse(Request $request)
    {
        $sortBy = $request->sort_by == 'latest' ? 'desc' : 'asc';
        $rating = $request->rating;
        $category = $request->category_id;
        $searchByTitle = $request->search_by_title;
        $courses = Course::with('category', 'subCategory', 'reviews')
            ->when($searchByTitle, function ($query, $searchByTitle) {
                return $query->where('title', 'like', '%' . $searchByTitle . '%');
            })
            ->when($category, function ($query, $category) {
                return $query->where('category_id', $category);
            })
            ->when($rating, function ($query, $rating) {
               return $query->whereHas('reviews', function ($q) use ($rating) {
                  $q->havingRaw('AVG(rating) >= ?', [$rating]);
               });
            })
            ->orderBy('created_at', $sortBy)
            ->paginate($request->per_page ?? 10);

        $courses->transform(function ($course) {
            return [
                'id' => $course->id,
                'title' => $course->title,
                'subtitle' => $course->subtitle,
                'slug' => $course->slug,
                'price' => $course->price,
                'category' => $course->category->name,
                'sub_category' => $course->subCategory->name,
                'topic' => $course->topic,
                'language' => $course->language,
                'c_level' => $course->c_level,
                'duration' => $course->duration,
                'thumbnail' => $course->thumbnail,
                'trailer_video' => $course->trailer_video,
                'description' => $course->description,
                'teach_course' => json_decode($course->teach_course),
                'targer_audience' => json_decode($course->targer_audience),
                'requirements' => json_decode($course->requirements),
                'total_enrollment' => $course->total_enrollment,
                'rating' => $course->reviews->avg('rating'),
                'total_reviews' => $course->reviews->count(),
                'created_at' => $course->created_at,
                'updated_at' => $course->updated_at,

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

    //store course
    public function storeCourse(Request $request)
    {
        try {
            // dd($request->all());
            $user = auth()->user();
            $request->validate([
                'title' => 'required|string|max:255',
                'subtitle' => 'required|string|max:255',
                'price' => 'required|numeric',
                'category_id' => 'required|exists:categories,id',
                'sub_category_id' => 'required|exists:sub_categories,id',
                'topic' => 'required|string',
                'language' => 'required|string',
                'c_level' => 'required|string',
                'duration' => 'required|integer',
                'thumbnail' => 'required|image|mimes:jpeg,png,jpg|dimensions:min_width=1200,min_height=800',
                'trailer_video' => 'required|file|mimes:mp4,avi,wmv',
                'description' => 'required|string',
                'teach_course' => 'required|array|max:8',
                'teach_course.*' => 'string|max:120',
                'targer_audience' => 'required|array|max:8',
                'targer_audience.*' => 'string|max:120',
                'requirements' => 'required|array|max:8',
                'requirements.*' => 'string|max:120',

            ]);

            $thumbnail = $request->file('thumbnail');
            $trailer_video = $request->file('trailer_video');

            if ($thumbnail && $trailer_video) {
                if (!file_exists(public_path('uploads/admin/course/thumbnail'))) {
                    mkdir(public_path('uploads/admin/course/thumbnail'), 0777, true);
                }
                $thumbnail_slug = Str::slug(pathinfo($thumbnail->getClientOriginalName(), PATHINFO_FILENAME));
                $thumbnail_slug = $thumbnail_slug . '.' . $thumbnail->extension();
                $thumbnail_name = time() . '_' . $thumbnail_slug;
                $thumbnail->move(public_path('uploads/admin/course/thumbnail'), $thumbnail_name);
                if (!file_exists(public_path('uploads/admin/course/video'))) {
                    mkdir(public_path('uploads/admin/course/video'), 0777, true);
                }
                $trailer_slug = Str::slug(pathinfo($trailer_video->getClientOriginalName(), PATHINFO_FILENAME));
                $trailer_slug = $trailer_slug . '.' . $trailer_video->extension();
                $trailer_video_name = time() . '_' . $trailer_slug;
                $trailer_video->move(public_path('uploads/admin/course/video'), $trailer_video_name);
            }
            //create url
            $thumbnail_url = url('uploads/admin/course/thumbnail/' . $thumbnail_name);
            $trailer_video_url = url('uploads/admin/course/video/' . $trailer_video_name);

            $course = new Course();
            $course->user_id = $user->id;
            $course->title = $request->title;
            $course->subtitle = $request->subtitle;
            $course->slug = generateUniqueSlug(Course::class, $request->title);
            $course->price = $request->price;
            $course->category_id = $request->category_id;
            $course->sub_category_id = $request->sub_category_id;
            $course->topic = $request->topic;
            $course->language = $request->language;
            $course->c_level = $request->c_level;
            $course->duration = $request->duration;
            $course->thumbnail = $thumbnail_url;
            $course->trailer_video = $trailer_video_url;
            $course->description = $request->description;
            $course->teach_course = json_encode($request->teach_course);
            $course->targer_audience = json_encode($request->targer_audience);
            $course->requirements = json_encode($request->requirements);
            $course->save();

            return response()->json([
                'success' => true,
                'message' => 'Course created successfully',
                'course' => $course,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    //show course
    public function showCourse($id)
    {
        $course = Course::with('category', 'subCategory')->find($id);
        $course->teach_course = json_decode($course->teach_course);
        $course->targer_audience = json_decode($course->targer_audience);
        $course->requirements = json_decode($course->requirements);
        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'course' => $course,
        ]);
    }

    //update course
    public function updateCourse(Request $request, $id)
    {
        try {
            $course = Course::find($id);
            if (!$course) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course not found',
                ], 404);
            }
            $user = auth()->user();
            $request->validate([
                'title' => 'required|string|max:255',
                'subtitle' => 'required|string|max:255',
                'price' => 'required|numeric',
                'category_id' => 'required|exists:categories,id',
                'sub_category_id' => 'required|exists:sub_categories,id',
                'topic' => 'required|string',
                'language' => 'required|string',
                'c_level' => 'required|string',
                'duration' => 'required|integer',
                'thumbnail' => 'required|image|mimes:jpeg,png,jpg|dimensions:min_width=1200,min_height=800',
                'trailer_video' => 'required|file|mimes:mp4,avi,wmv',
                'description' => 'required|string',
                'teach_course' => 'required|array|max:8',
                'teach_course.*' => 'string|max:120',
                'targer_audience' => 'required|array|max:8',
                'targer_audience.*' => 'string|max:120',
                'requirements' => 'required|array|max:8',
                'requirements.*' => 'string|max:120',

            ]);

            $thumbnail = $request->file('thumbnail');
            $trailer_video = $request->file('trailer_video');
            // dd($thumbnail, $trailer_video);
            if ($thumbnail && $trailer_video) {

                //unlink old file
                $thumbnail_path = parse_url($course->thumbnail);
                $thumbnail_path = public_path($thumbnail_path['path']);
                if (file_exists($thumbnail_path)) {
                    unlink($thumbnail_path);
                }
                $trailer_video_path = parse_url($course->trailer_video);
                $trailer_video_path = public_path($trailer_video_path['path']);
                if (file_exists($trailer_video_path)) {
                    unlink($trailer_video_path);
                }

                if (!file_exists(public_path('uploads/admin/course/thumbnail'))) {
                    mkdir(public_path('uploads/admin/course/thumbnail'), 0777, true);
                }
                $thumbnail_slug = Str::slug(pathinfo($thumbnail->getClientOriginalName(), PATHINFO_FILENAME));
                $thumbnail_slug = $thumbnail_slug . '.' . $thumbnail->extension();
                $thumbnail_name = time() . '_' . $thumbnail_slug;
                $thumbnail->move(public_path('uploads/admin/course/thumbnail'), $thumbnail_name);
                if (!file_exists(public_path('uploads/admin/course/video'))) {
                    mkdir(public_path('uploads/admin/course/video'), 0777, true);
                }

                $trailer_slug = Str::slug(pathinfo($trailer_video->getClientOriginalName(), PATHINFO_FILENAME));
                $trailer_slug = $trailer_slug . '.' . $trailer_video->extension();
                $trailer_video_name = time() . '_' . $trailer_slug;
                $trailer_video->move(public_path('uploads/admin/course/video'), $trailer_video_name);
            }
            //create url
            $thumbnail_url = url('uploads/admin/course/thumbnail/' . $thumbnail_name);
            $trailer_video_url = url('uploads/admin/course/video/' . $trailer_video_name);

            $course->user_id = $user->id;
            $course->title = $request->title;
            $course->subtitle = $request->subtitle;
            $course->slug = generateUniqueSlug(Course::class, $request->title);
            $course->price = $request->price;
            $course->category_id = $request->category_id;
            $course->sub_category_id = $request->sub_category_id;
            $course->topic = $request->topic;
            $course->language = $request->language;
            $course->c_level = $request->c_level;
            $course->duration = $request->duration;
            $course->thumbnail = $thumbnail_url;
            $course->trailer_video = $trailer_video_url;
            $course->description = $request->description;
            $course->teach_course = json_encode($request->teach_course);
            $course->targer_audience = json_encode($request->targer_audience);
            $course->requirements = json_encode($request->requirements);
            $course->save();

            return response()->json([
                'success' => true,
                'message' => 'Course updated successfully',
                'course' => $course,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    //destroy course
    public function destroyCourse($id)
    {
        $course = Course::find($id);
        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found',
            ], 404);
        }
        //unlink old file
        $thumbnail_path = parse_url($course->thumbnail);
        $thumbnail_path = public_path($thumbnail_path['path']);
        if (file_exists($thumbnail_path)) {
            unlink($thumbnail_path);
        }
        $trailer_video_path = parse_url($course->trailer_video);
        $trailer_video_path = public_path($trailer_video_path['path']);
        if (file_exists($trailer_video_path)) {
            unlink($trailer_video_path);
        }
        $course->delete();
        return response()->json([
            'success' => true,
            'message' => 'Course deleted successfully',
        ]);
    }

    //search course in online program section
    // public function searcCourse(Request $request){


    // }

    /*==============
    End Course section
    ==============*/

    /*==============
    Curriculam Section
    =================*/

    //get curriculum
    public function getCurriculum($course_id)
    {
        $course = Course::find($course_id);
        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found',
            ], 404);
        }
        $curriculum = $course->curriculum;
        if ($curriculum->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Curriculum not found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'curriculum' => $curriculum,
        ]);
    }

    //store curriculum
    public function storeCurriculum(Request $request, $course_id)
    {
        $course = Course::find($course_id);
        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found',
            ], 404);
        }
        $validator = Validator::make($request->all(), [
            'section_name' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors(),
            ], 400);
        }
        $curriculum = $course->curriculum()->create([
            'section_name' => $request->section_name,
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Curriculum created successfully',
            'curriculum' => $curriculum,
        ]);
    }

    public function updateCurriculum(Request $request, $id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json([
                'success' => false,
                'message' => 'Curriculum not found',
            ], 404);
        }
        $validator = Validator::make($request->all(), [
            'section_name' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors(),
            ], 400);
        }
        $curriculum->section_name = $request->section_name;
        $curriculum->save();
        return response()->json([
            'success' => true,
            'message' => 'Curriculum updated successfully',
            'curriculum' => $curriculum,
        ]);
    }

    //destroy curriculum
    public function destroyCurriculum($id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json([
                'success' => false,
                'message' => 'Curriculum not found',
            ], 404);
        }
        $curriculum->delete();
        return response()->json([
            'success' => true,
            'message' => 'Curriculum deleted successfully',
        ]);
    }

    /*======================
    End Curriculum Section
    ========================*/

    /*======================
    Lesson Section
    ========================*/

    //get lesson
    public function getLecture($curriculum_id)
    {
        $curriculum = Curriculum::find($curriculum_id);
        if (!$curriculum) {
            return response()->json([
                'success' => false,
                'message' => 'Curriculum not found',
            ], 404);
        }
        $lectures = $curriculum->lectures;
        if ($lectures->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Lesson not found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'lectures' => $lectures,
        ]);
    }

    //store lesson
    public function storeLecture(Request $request, $curriculum_id)
    {
        $curriculum = Curriculum::find($curriculum_id);
        if (!$curriculum) {
            return response()->json([
                'success' => false,
                'message' => 'Curriculum not found',
            ], 404);
        }
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'video_url' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors(),
            ], 400);
        }

        $lecture = $curriculum->lectures()->create([
            'title' => $request->title,
            'slug' => generateUniqueSlug(Lecture::class, $request->title),
            'description' => $request->description,
            'video_url' => $request->video_url,
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Lesson created successfully',
            'lecture' => $lecture,
        ], 201);
    }

    //update lesson
    public function updateLecture(Request $request, $id)
    {
        $lecture = Lecture::findOrFail($id);
        if (!$lecture) {
            return response()->json([
                'success' => false,
                'message' => 'Lesson not found',
            ], 404);
        }
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'video_url' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors(),
            ], 400);
        }
        $lecture->title = $request->title;
        $lecture->slug = generateUniqueSlug(Lecture::class, $request->title);
        $lecture->description = $request->description;
        $lecture->video_url = $request->video_url;
        $lecture->save();
        return response()->json([
            'success' => true,
            'message' => 'Lesson updated successfully',
            'lecture' => $lecture,
        ], 201);
    }

    //destroy lesson
    public function destroyLecture($id)
    {
        $lecture = Lecture::find($id);
        if (!$lecture) {
            return response()->json([
                'success' => false,
                'message' => 'Lesson not found',
            ], 404);
        }
        $lecture->delete();
        return response()->json([
            'success' => true,
            'message' => 'Lesson deleted successfully',
        ], 200);
    }
}
