<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Akudihatinya') }}</title>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="{{ asset('assets/index-CrqBaFy_.css') }}" as="style">
    <link rel="preload" href="{{ asset('assets/index-BAhSsepR.js') }}" as="script">
    
    <!-- CSS -->
    <link rel="stylesheet" href="{{ asset('assets/index-CrqBaFy_.css') }}">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
</head>
<body>
    <!-- Vue.js App Mount Point -->
    <div id="app"></div>
    
    <!-- JavaScript -->
    <script src="{{ asset('assets/index-BAhSsepR.js') }}"></script>
</body>
</html>