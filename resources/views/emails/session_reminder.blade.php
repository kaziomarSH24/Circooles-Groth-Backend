@component('mail::message')
# Hello {{ $recipientType === 'student' ? $session['student_name'] : $session['tutor_name'] }}

This is a reminder for your upcoming session
@if($recipientType === 'student')
with **{{ $session['tutor_name'] }}**.
@else
with **{{ $session['student_name'] }}**.
@endif

## Session Details:
- **Date:** {{ \Carbon\Carbon::parse($session['start_time'])->toFormattedDateString() }}
- **Time:** {{ \Carbon\Carbon::parse($session['start_time'])->format('h:i A') }}
- **Time Left:** {{ $session['time_left'] }}
- **Duration:** {{ $session['duration'] }} hours
- **Mode:** {{ ucfirst($session['type']) }}

@if(isset($session['zoom_link']))
@component('mail::button', ['url' => $session['zoom_link']])
Join Session
@endcomponent
@endif

Please make sure to join on time.
Thank you for choosing our platform!

Best Regards,
{{ config('app.name') }}
@endcomponent
