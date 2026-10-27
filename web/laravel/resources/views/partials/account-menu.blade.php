{{-- The logged-in user's menu in the site header: a native disclosure, so it works without
     JavaScript (site.js only closes it on a click outside or Escape). --}}
@php($user = auth()->user())
<details class="account-menu">
    <summary class="account-menu__button">
        @include('partials.avatar', ['user' => $user])
        <span class="account-menu__name">{{ $user->firstName() }}</span>
        <svg class="account-menu__chevron" width="14" height="14" viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 6l4.5 4.5L12.5 6"/></svg>
        <span class="visually-hidden">Your account menu</span>
    </summary>
    <div class="account-menu__panel">
        <p class="account-menu__who"><strong>{{ $user->name }}</strong><br><span>{{ $user->email }}</span></p>
        <nav aria-label="Your account">
            <a href="{{ route('account.dashboard') }}">Your account</a>
            <a href="{{ route('account.profile') }}">Profile and photo</a>
            <a href="{{ route('account.security') }}">Security</a>
        </nav>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button class="account-menu__logout" type="submit">Log out</button>
        </form>
    </div>
</details>
