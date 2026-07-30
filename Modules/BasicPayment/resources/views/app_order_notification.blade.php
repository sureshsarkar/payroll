<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $title }}</title>
    <link rel="stylesheet" href="{{ asset('frontend/css/bootstrap.min.css') }}">
</head>

<body>
    <div class="w-100 h-100 position-absolute">
        <div class="row d-flex justify-content-center align-items-center h-100 w-100">
            <div class="text-center p-4">
                <img src="{{ asset('uploads/website-images/' . $image) }}">
                <h4 class="mt-2">{{ $title }}</h4>
                <p>{{ $sub_title }}</p>
            </div>
        </div>
    </div>
</body>

</html>