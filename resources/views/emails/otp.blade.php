@component('mail::message')
# OTP Verification

Your OTP code is **{{ $otp }}**.

Please use this code to verify your account. This code will expire in **{{ $data['otp_expiry_time'] }}** minutes.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
