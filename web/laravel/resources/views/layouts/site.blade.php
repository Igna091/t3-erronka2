<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', config('app.name'))</title>
        <meta name="description" content="@yield('description')">

        {{-- Everything comes from this site: the CSP (web/nginx/snippets/security-headers.conf) allows
             no third-party fonts, inline styles or inline scripts. --}}
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="preload" href="{{ asset('fonts/archivo/archivo-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
        <link rel="stylesheet" href="{{ versioned_asset('css/site.css') }}">
        @stack('styles')
        <script src="{{ versioned_asset('js/site.js') }}" defer></script>
        @stack('scripts')
    </head>
    <body>
        <a class="skip-link" href="#main">Skip to content</a>
        @yield('content')
    </body>
</html>
