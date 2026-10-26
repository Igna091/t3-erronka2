@extends('layouts.site')

@section('title', 'Shop · '.config('app.name'))
@section('description', 'Medicines that don’t need a prescription, ordered online and collected at the pharmacy counter.')

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/shop.css') }}">
@endpush

@section('content')
    @include('partials.demo-bar')
    @include('partials.header', ['shop' => true])

    <main id="main" class="shop-main">
        <section class="shop-intro wrap">
            <div class="shop-intro__text">
                <h1>Medicines without a prescription</h1>
                <p class="lead">For pain, fever, heartburn, coughs and small cuts. Order here and collect it at the counter, usually within {{ config('shop.ready_within') }}.</p>
                <p class="small muted">Prescription medicines can’t be sold online. <a href="{{ route('home') }}#refills">Send us your prescription</a> and collect them at the counter.</p>
            </div>
            <x-capsule class="shop-intro__capsule" progress="0" />
        </section>

        <section class="wrap" aria-label="How buying online works">
            <ul class="rules">
                <li>
                    <span class="dot dot--blue" aria-hidden="true"></span>
                    <div>
                        <p class="rules__title">A pharmacist checks every order</p>
                        <p>If a medicine isn’t right for you, we call you before preparing it.</p>
                    </div>
                </li>
                <li>
                    <span class="dot dot--yellow" aria-hidden="true"></span>
                    <div>
                        <p class="rules__title">Up to {{ $basket->max() }} packs of each medicine</p>
                        <p>The most we sell in one order.</p>
                    </div>
                </li>
                <li>
                    <span class="dot dot--white" aria-hidden="true"></span>
                    <div>
                        <p class="rules__title">Collect it at the counter</p>
                        <p>We text you when it’s ready. We don’t deliver.</p>
                    </div>
                </li>
            </ul>
        </section>

        @include('shop.partials.flash')

        <section class="catalogue wrap" aria-labelledby="catalogue-title">
            <div class="catalogue__head">
                <h2 id="catalogue-title">Choose by what it’s for</h2>
                <nav class="chips" aria-label="What it’s for">
                    <a @class(['chip', 'is-selected' => $category === null]) href="{{ route('shop.index') }}" @if ($category === null) aria-current="page" @endif>All</a>
                    @foreach ($categories as $key => $info)
                        <a @class(['chip', 'is-selected' => $category === $key]) href="{{ route('shop.index', ['category' => $key]) }}" @if ($category === $key) aria-current="page" @endif>{{ $info['label'] }}</a>
                    @endforeach
                </nav>
            </div>

            <ul class="products">
                @foreach ($medicines as $medicine)
                    @php($inBasket = $basket->quantity($medicine))
                    <li class="product" id="{{ $medicine->slug }}">
                        <div class="product__picture"><x-pack :medicine="$medicine" /></div>
                        <div class="product__text">
                            <p class="category"><span class="dot dot--{{ $medicine->category }}" aria-hidden="true"></span>{{ $medicine->categoryLabel() }}</p>
                            <h3><a href="{{ route('shop.show', $medicine) }}">{{ $medicine->name }}</a></h3>
                            <p class="muted">{{ $medicine->pack }}</p>
                            <p>{{ $medicine->summary }}</p>
                        </div>
                        <div class="product__buy">
                            <p class="price">{{ euros($medicine->price_cents) }}</p>
                            @if ($inBasket < $basket->max())
                                <form method="post" action="{{ route('basket.store', $medicine) }}">
                                    @csrf
                                    <button class="btn btn--primary btn--small" type="submit">{{ $inBasket === 0 ? 'Add to basket' : 'Add another' }}</button>
                                </form>
                            @else
                                <button class="btn btn--done btn--small" type="button" disabled>Limit reached</button>
                            @endif
                        </div>
                        @if ($inBasket > 0)
                            <p class="product__note">{{ $inBasket < $basket->max() ? $inBasket.' in your basket' : $inBasket.' in your basket, the most per order' }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    </main>

    @include('partials.footer')
@endsection
