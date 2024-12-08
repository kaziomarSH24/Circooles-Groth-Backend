<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $data['title'] }}</title>
</head>
<body>
    <h1>{{$data['title']}}</h1>
    <p>Your OTP code is: {{ $data['otp'] }}</p>
    <p>This OTP will expire in {{ $data['otp_expiry_time'] }} minutes.</p>
</body>
</html>