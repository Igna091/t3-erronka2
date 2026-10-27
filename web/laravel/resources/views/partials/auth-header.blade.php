{{-- The slim header of the login and sign-up pages: the brand, and on the right an optional
     "$asidePrefix <a href=$asideLink>$asideText</a>", such as "New here? Create an account". --}}
<header class="site-header site-header--slim wrap">
    <a class="brand" href="{{ route('home') }}">
        <svg width="34" height="18" viewBox="0 0 34 18" aria-hidden="true"><rect x="1" y="2" width="20" height="14" rx="7" fill="#F2C94C"/><path d="M15 1h10a8 8 0 0 1 0 16H15z" fill="#2A4BD7"/></svg>
        {{ config('app.name') }}
    </a>
    @isset($asideLink)
        <p class="auth-aside">{{ $asidePrefix ?? '' }} <a href="{{ $asideLink }}">{{ $asideText }}</a></p>
    @endisset
</header>
