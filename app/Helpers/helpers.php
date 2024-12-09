<?php

use App\Mail\sendOtp;
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
