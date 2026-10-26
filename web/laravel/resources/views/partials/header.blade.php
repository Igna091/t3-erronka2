{{-- Site header. $status and $basketCount come from the view composer in AppServiceProvider;
     pass ['shop' => true] to show the basket instead of "Send a prescription". --}}
<header class="site-header wrap">
    <a class="brand" href="{{ route('home') }}">
        <svg width="34" height="18" viewBox="0 0 34 18" aria-hidden="true"><rect x="1" y="2" width="20" height="14" rx="7" fill="#F2C94C"/><path d="M15 1h10a8 8 0 0 1 0 16H15z" fill="#2A4BD7"/></svg>
        {{ config('app.name') }}
    </a>

    <nav class="site-nav" aria-label="Main">
        @include('partials.nav-links')
    </nav>

    <div class="header-actions">
        <p @class(['status', 'is-closed' => ! $status['open']])>
            <svg class="cross" width="16" height="16" viewBox="0 0 18 18" aria-hidden="true"><path d="M6 0h6v6h6v6h-6v6H6v-6H0V6h6z" fill="currentColor"/></svg>
            @if ($status['open'])
                Open until {{ $status['closes'] }}
            @elseif ($status['opens'])
                Opens {{ $status['opens'] }}
            @else
                Closed
            @endif
        </p>
        @if ($shop ?? false)
            <a class="basket-button" href="{{ route('basket.show') }}" aria-label="Basket, {{ $basketCount }} {{ $basketCount === 1 ? 'item' : 'items' }}" @if (request()->routeIs('basket.*')) aria-current="page" @endif>
                <svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M3 7h14l-1.2 10H4.2z"/><path d="M7 7V5a3 3 0 0 1 6 0v2" stroke-linecap="round"/></svg>
                <span class="basket-button__label">Basket</span>
                <span class="basket-button__count">{{ $basketCount }}</span>
            </a>
        @else
            <a class="btn btn--primary btn--small header-cta" href="{{ route('home') }}#refills">Send a prescription</a>
        @endif
    </div>

    {{-- Phone menu: a native disclosure, so it opens without JavaScript --}}
    <details class="menu">
        <summary class="menu-button" aria-label="Menu">
            <svg width="20" height="14" viewBox="0 0 20 14" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 2h18M1 12h18"/></svg>
        </summary>
        <nav class="menu-panel" aria-label="Main">
            @include('partials.nav-links')
        </nav>
    </details>
</header>
