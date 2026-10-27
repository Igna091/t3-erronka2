{{-- The account area's own menu, beside its pages (a row above them on a phone). --}}
<nav class="account-nav" aria-label="Your account">
    <p class="account-nav__who">{{ auth()->user()->name }}</p>
    <a href="{{ route('account.dashboard') }}" @if (request()->routeIs('account.dashboard')) aria-current="page" @endif>Overview</a>
    <a href="{{ route('account.profile') }}" @if (request()->routeIs('account.profile')) aria-current="page" @endif>Profile and photo</a>
    <a href="{{ route('account.security') }}" @if (request()->routeIs('account.security', 'account.password', 'account.two-step*', 'account.recovery-codes')) aria-current="page" @endif>Security</a>
    <form method="post" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Log out</button>
    </form>
</nav>
