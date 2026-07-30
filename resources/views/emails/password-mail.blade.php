<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php($brand = $brandName ?? config('app.name'))
    <title>Welcome - {{ $brand }}</title>
</head>
<body style="font-family:Helvetica,Arial,sans-serif; color:#1f2937;">
    @if(!empty($brandLogoUrl))
        <p><img src="{{ $brandLogoUrl }}" alt="{{ $brand }}" style="max-height:48px;"></p>
    @else
        <p><strong style="font-size:18px; color:#1c1a4a;">{{ $brand }}</strong></p>
    @endif

    <p>Hello {{ $user->name }},</p>

    <p>Welcome to {{ $brand }}! Your account has been created successfully.</p>

    <p>Your password: <strong>{{ $password }}</strong></p>

    @php($loginUrl = brandedUrl(config('app.url'), $user->coach_id ?? null))
    <p>You can log in to your account at
        <a href="{{ $loginUrl }}" style="color:{{ $brandColor ?? '#0867ec' }};">{{ $loginUrl }}</a>
    </p>

    <p>Thank you for joining us.</p>
</body>
</html>
