<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    /*==============
    Course Controller
    ==============*/

    //get course
    public function indexCourse(Request $request)
    {
        $courses = Course::with('category', 'subCategory')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10);

        $courses->transform(function ($course) {
            return [
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
    public function updateCourse(Request $request, $id){
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
                $trailer_video_name = time() . '_' .$trailer_slug;
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

    /*==============
    End Course Controller
    ==============*/

    /*==============
    Curriculam Controller
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

    
}
