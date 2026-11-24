<!DOCTYPE html>
<html lang="ro">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <meta name="theme-color" content="#176f56">
 <meta name="csrf-token" content="{{ csrf_token() }}">
 @if(\Illuminate\Support\Facades\Vite::cspNonce())<meta name="csp-nonce" content="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">@endif
 <link rel="icon" href="/favicon.ico" type="image/x-icon" sizes="16x16 32x32 48x48 64x64 128x128 256x256">
 <link rel="icon" href="/favicon.svg" type="image/svg+xml" sizes="any">
 <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
 <link rel="manifest" href="/manifest.webmanifest">
 @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
 <x-inertia::head>{!! implode("\n", app(\App\Services\SeoMetadata::class)->head(request())) !!}</x-inertia::head>
</head>
<body class="font-sans antialiased">
@if($page['component'] === 'Welcome')
 <script data-page="app" type="application/json">{!! json_encode($page, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
 <div id="app">@include('seo.home')</div>
@else
 <x-inertia::app />
@endif
</body>
</html>
