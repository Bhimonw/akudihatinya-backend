<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>akudihatinya</title>

    @php($fa = $frontendAssets ?? null)
    @if($fa)
        <!-- Preload critical resources -->
        <link rel="preload" href="{{ $fa['css'] }}" as="style">
        <link rel="preload" href="{{ $fa['js'] }}" as="script">
        <!-- CSS -->
        <link rel="stylesheet" href="{{ $fa['css'] }}?v={{ $fa['version'] }}">
    @endif
    
    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="{{ asset('ptm-icon.jpg') }}">

</head>
<body>
    <!-- Vue.js App Mount Point -->
    <div id="app"></div>
    
    <script>
        window.__RUNTIME_CONFIG = {
            apiBase: '{{ rtrim(config('app.url'), '/') }}/api',
            appName: '{{ config('app.name', 'akudihatinya') }}',
            buildVersion: '{{ $fa['version'] ?? 'dev' }}'
        };
    </script>
    @if($fa)
        <script type="module" src="{{ $fa['js'] }}?v={{ $fa['version'] }}"></script>
    @endif
</body>
</html>