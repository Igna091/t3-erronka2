@extends('layouts.site')

@section('title', $medicine->name.' · '.config('app.name'))
@section('description', $medicine->name.', '.$medicine->pack.'. '.$medicine->summary.'.')

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/shop.css') }}">
@endpush
@push('scripts')
    <script src="{{ versioned_asset('js/shop.js') }}" defer></script>
@endpush

@section('content')
    @include('partials.demo-bar')
    @include('partials.header', ['shop' => true])

    <main id="main" class="shop-main">
        <nav class="breadcrumb wrap" aria-label="Breadcrumb">
            <ol>
                <li><a href="{{ route('shop.index') }}">Shop</a></li>
                <li><a href="{{ route('shop.index', ['category' => $medicine->category]) }}">{{ $medicine->categoryLabel() }}</a></li>
                <li aria-current="page">{{ $medicine->name }}</li>
            </ol>
        </nav>

        @include('shop.partials.flash')

        <section class="medicine wrap">
            <div class="medicine__picture"><x-pack :medicine="$medicine" /></div>

            <div class="medicine__buy">
                <div class="medicine__title">
                    <p class="category"><span class="dot dot--{{ $medicine->category }}" aria-hidden="true"></span>{{ $medicine->categoryLabel() }}</p>
                    <h1>{{ $medicine->name }}</h1>
                    <p class="medicine__pack">{{ $medicine->pack }}</p>
                </div>
                <p>For {{ lcfirst($medicine->summary) }}.</p>
                <p class="medicine__price">{{ euros($medicine->price_cents) }}</p>

                @if ($inBasket < $max)
                    <form class="add-form" method="post" action="{{ route('basket.store', $medicine) }}">
                        @csrf
                        <input type="hidden" name="next" value="basket">
                        <div class="field">
                            <label for="quantity">Quantity</label>
                            <div class="stepper" data-stepper>
                                <button class="stepper__button" type="button" data-step="-1" aria-label="One pack fewer" hidden>
                                    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 8h10"/></svg>
                                </button>
                                <input class="stepper__input" id="quantity" name="quantity" type="number" inputmode="numeric" min="1" max="{{ $max - $inBasket }}" value="1">
                                <button class="stepper__button" type="button" data-step="1" aria-label="One pack more" hidden>
                                    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 8h10M8 3v10"/></svg>
                                </button>
                            </div>
                            <p class="field__help">
                                @if ($inBasket > 0)
                                    You already have {{ $inBasket }} in your basket; up to {{ $max }} packs per order.
                                @else
                                    You can buy up to {{ $max }} packs in one order.
                                @endif
                            </p>
                        </div>
                        <button class="btn btn--primary" type="submit">Add to basket</button>
                    </form>
                @else
                    <p class="notice">You have {{ $max }} packs in your basket, the most per order. <a href="{{ route('basket.show') }}">Go to your basket</a></p>
                @endif

                <ul class="info-list">
                    <li><span class="dot dot--blue" aria-hidden="true"></span>A pharmacist checks your order before we prepare it.</li>
                    <li><span class="dot dot--yellow" aria-hidden="true"></span>Collect it at the counter, usually within {{ config('shop.ready_within') }}. We don’t deliver.</li>
                </ul>
            </div>
        </section>

        <section class="medicine-details wrap">
            <div class="medicine-details__inner">
                <div>
                    <h2>What it’s for</h2>
                    <p>{{ $medicine->description }}</p>
                </div>
                <div>
                    <h2>How to take it</h2>
                    <p class="muted">{{ $medicine->dosage }}</p>
                </div>
                <div>
                    <h2>Before you take it</h2>
                    <ul class="info-list">
                        @foreach ($medicine->warnings as $warning)
                            <li @class(['muted' => str_starts_with($warning, '[')])><span class="dot dot--white" aria-hidden="true"></span>{{ $warning }}</li>
                        @endforeach
                    </ul>
                    <p><a href="{{ config('shop.leaflet_url') }}">Read the full package leaflet on CIMA</a>, the Spanish Medicines Agency’s official database.</p>
                </div>
                <div class="required-notice">
                    <svg width="28" height="28" viewBox="0 0 18 18" aria-hidden="true"><path d="M6 0h6v6h6v6h-6v6H6v-6H0V6h6z" fill="#1DB45A"/></svg>
                    <p>Read the instructions of this medicine and ask your pharmacist.</p>
                </div>
            </div>
        </section>
    </main>

    @include('partials.footer')
@endsection
