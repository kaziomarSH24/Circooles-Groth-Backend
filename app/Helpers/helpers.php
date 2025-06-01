<?php

use App\Mail\ScheduleMail;
use App\Mail\sendOtp;
use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;


//send otp
if (!function_exists('sendOtp')) {
    function sendOtp(array $data, $otp_expiry_time = 10)
    {
        $otp = generateOtp();
        $otp_expiry_at = Carbon::now()->addMinutes($otp_expiry_time)
            ->format('Y-m-d H:i:s');
        $data = [
            'email' => $data['email'],
            'title' => 'Email Verification',
            'otp' => $otp,
            'otp_expiry_at' => $otp_expiry_at,
            'otp_expiry_time' => $otp_expiry_time,
        ];
        Mail::to($data['email'])->send(new sendOtp($data));
        return $data;
    }
}

//Session Schedule Mail
if (!function_exists('scheduleMail')) {
    function scheduleMail($data)
    {
        Mail::to($data['email'])->send(new ScheduleMail($data));
    }
}

//generate otp
if (!function_exists('generateOtp')) {
    function generateOtp($length = 4)
    {
        $otp = str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
        return $otp;
    }
}

//generate slug
if (!function_exists('generateSlug')) {
    function generateSlug($string)
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $string));
        $slug = rtrim($slug, '-');
        return $slug;
    }
}

//generate unique slug
if (!function_exists('generateUniqueSlug')) {
    function generateUniqueSlug($model, $string)
    {
        $slug = generateSlug($string);
        $originalSlug = $slug;
        $count = 1;
        while ($model::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }
        return $slug;
    }
}

//create transaction reference unique id
if (!function_exists('referenceId')) {
    function referenceId()
    {
        return 'circooles_' . uniqid();
    }
}
/**
 * Total indeviual course lectures count
 */

if (!function_exists('totalLecturesCount')) {
    function totalCourseLecturesCount($course_id)
    {
        $course = Course::find($course_id);

        $lectures = $course->curriculum->map(function ($curriculum) {
            return $curriculum->lectures->count();
        });
        return $lectures->sum();
    }
}

/**
 * Get course lectures ids
 */
if (!function_exists('courseLecturesIds')) {
    function courseLecturesIds($course_id)
    {
        $course = Course::find($course_id);

        $lectureIds = $course->curriculum->flatMap(function ($curriculum) {
            return $curriculum->lectures->pluck('id');
        });

        return $lectureIds;
    }
}

//Get youtube video id from url
if (!function_exists('getYoutubeVideoId')) {
    /**
     * Extracts the YouTube video ID from a given URL.
     *
     * @param string $url The YouTube video URL.
     * @return string|null The extracted video ID or null if not found.
     */
    function getYoutubeVideoId($url)
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        return $params['v'] ?? null;
    }
}
