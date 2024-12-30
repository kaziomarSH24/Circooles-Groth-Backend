@component('mail::message')
# {{ $isStudent ? 'Refund Processed' : 'Penalty for Lateness' }}

@if($isStudent)
Dear {{ $session['student_name'] }},

We have processed a refund of **${{ $refundAmount }}** to your wallet due to the tutor's lateness.

@else
Dear {{ $session['tutor_name'] }},

A penalty of **${{ $refundAmount }}** has been deducted from your wallet for being late to the session with **{{ $session['student_name'] }}**.

@endif

If you have any questions, feel free to contact us.

@component('mail::button', ['url' => config('app.url')])
Visit Platform
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
