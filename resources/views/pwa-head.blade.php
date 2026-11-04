<meta name="theme-color" content="#0b4f91">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Motel">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/motel-180.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/motel-192.png') }}">
<link rel="stylesheet" href="{{ asset('pwa.css') }}?v={{ filemtime(public_path('pwa.css')) }}">
<script src="{{ asset('pwa.js') }}?v={{ filemtime(public_path('pwa.js')) }}" defer></script>
