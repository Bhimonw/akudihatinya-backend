<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>akudihatinya</title>

    @php($fa = $frontendAssets ?? null)
    @if($fa && $fa['css'])
        <!-- Preload critical resources -->
        <link rel="preload" href="{{ $fa['css'] }}" as="style">
        @if($fa['js'])<link rel="preload" href="{{ $fa['js'] }}" as="script">@endif
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
        // Unified runtime config for frontend bundle
        (function(){
            const configured = @json(config('frontend.api_base'));
            const apiBase = (configured && configured.trim() !== '')
                ? configured.replace(/\/$/, '')
                : (window.location.origin + '/api');
            window.__RUNTIME_CONFIG__ = {
                API_BASE_URL: apiBase,
                APP_NAME: '{{ config('app.name', 'akudihatinya') }}',
                BUILD_VERSION: '{{ $fa['version'] ?? 'dev' }}'
            };
        })();
    </script>
    @if($fa && $fa['js'])
        <script type="module" src="{{ $fa['js'] }}?v={{ $fa['version'] }}"></script>
    @else
        <noscript>Frontend assets tidak ditemukan. Pastikan sudah menjalankan build Vite.</noscript>
    @endif
</body>
</html>