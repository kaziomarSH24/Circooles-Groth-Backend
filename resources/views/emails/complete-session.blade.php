@component('mail::message')
# Hello, {{ $data['tutor_name'] }}!

We are pleased to inform you that your **today's** session has been successfully completed.

Your payment has been processed and is now available in your wallet. Below are the session details:

---

### Session Details:
- **Student Name:** {{ $data['student_name'] }}
- **Session Date & Time:** {{ $data['date'] }} ({{ $data['time'] }})
- **Total Payment:** ${{ $data['total_payment'] }}

---

To check your wallet balance, please log in to our platform.

@component('mail::button', ['url' => config('app.url')])
Go to Dashboard
@endcomponent

### Note:
If you have any questions regarding this payment or need assistance, please contact our support team.

Thank you,
The {{ config('app.name') }} Team

@endcomponent

