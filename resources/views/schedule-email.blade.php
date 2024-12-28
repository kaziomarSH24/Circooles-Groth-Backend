<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{$data['title']}}</title>
</head>
<body>

    <h1>{{$data['title']}}</h1>
    <p>Dear {{$data['name']}},</p>
    <p>{{$data['content']}}</p>
    <p>Here are the details:</p>
    <p>Subject: {{$data['title']}}</p>
    <p>Booking Time: {{$data['booking_time']}}</p>
    <p>Session Quantity: {{$data['session_quantity']}}</p>
    <p>Total Cost: ${{$data['total_cost']}}</p>
    <p>First Session Date: {{$data['start_date']}}</p>
    <p>Thank you for using our application.</p>
    <p>Best Regards,</p>
    <p>Team {{config('app.name')}}</p>
    <p><small>This is an auto-generated email. Please do not reply to this email.</small></p>

</body>
</html>
