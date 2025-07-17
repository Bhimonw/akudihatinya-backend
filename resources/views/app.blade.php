<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>akudihatinya</title>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="{{ asset('assets/index-BAAp1Kbd.css') }}" as="style">
    <link rel="preload" href="{{ asset('assets/index-DjSQ9tTq.js') }}" as="script">
    
    <!-- CSS -->
    <link rel="stylesheet" href="{{ asset('assets/index-BAAp1Kbd.css') }}">
    
    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="{{ asset('ptm-icon.jpg') }}">
</head>
<body>
    <!-- Vue.js App Mount Point -->
    <div id="app"></div>
    
    <!-- JavaScript -->
    <script type="module" src="{{ asset('assets/index-DjSQ9tTq.js') }}"></script>
</body>
</html>