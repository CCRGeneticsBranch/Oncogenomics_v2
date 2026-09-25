<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <link rel="stylesheet" href="{{ url('css/bootstrap.min.css') }}">
    <style>
        html, body { width: 100%; height: 100%; margin: 0; overflow: hidden; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
