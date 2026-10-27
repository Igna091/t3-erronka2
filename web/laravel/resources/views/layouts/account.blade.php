{{-- Login, sign-up and the account pages: the site's layout with the form styles (shop.css),
     account.css and account.js. A page fills "header" (partials.auth-header or partials.header)
     and "main". --}}
@extends('layouts.site')

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/shop.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/account.css') }}">
@endpush
@push('scripts')
    <script src="{{ versioned_asset('js/account.js') }}" defer></script>
@endpush

@section('content')
    @yield('header')

    <main id="main" class="account-main">
        @yield('main')
    </main>

    @include('partials.footer')
@endsection
