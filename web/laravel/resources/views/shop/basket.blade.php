@extends('layouts.site')

@section('title', 'Your basket · '.config('app.name'))

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/shop.css') }}">
@endpush

@section('content')
    @include('partials.demo-bar')
    @include('partials.header', ['shop' => true])

    <main id="main" class="shop-main">
        <section class="page-title wrap">
            <h1>Your basket</h1>
            @if ($lines->isNotEmpty())
                @php($packs = $lines->sum('quantity'))
                <p class="muted">{{ $lines->count() }} {{ $lines->count() === 1 ? 'medicine' : 'medicines' }}, {{ $packs }} {{ $packs === 1 ? 'pack' : 'packs' }}</p>
            @endif
        </section>

        @include('shop.partials.flash')

        @if ($lines->isEmpty())
            <section class="wrap">
                <div class="empty">
                    <p>Your basket is empty.</p>
                    <a class="btn btn--primary" href="{{ route('shop.index') }}">Browse medicines</a>
                </div>
            </section>
        @else
            <div class="with-summary wrap">
                <ul class="basket">
                    @foreach ($lines as $line)
                        @php($medicine = $line['medicine'])
                        <li class="basket-line">
                            <div class="basket-line__picture"><x-pack :medicine="$medicine" /></div>
                            <div class="basket-line__text">
                                <h2><a href="{{ route('shop.show', $medicine) }}">{{ $medicine->name }}</a></h2>
                                <p class="muted">{{ $medicine->pack }}</p>
                                <p class="muted">{{ euros($medicine->price_cents) }} each</p>
                            </div>
                            <div class="basket-line__quantity">
                                <div class="stepper" role="group" aria-label="Packs of {{ $medicine->name }}">
                                    <form method="post" action="{{ route('basket.update', $medicine) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="quantity" value="{{ $line['quantity'] - 1 }}">
                                        <button class="stepper__button" type="submit" aria-label="One pack fewer" @disabled($line['quantity'] <= 1)>
                                            <svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 8h10"/></svg>
                                        </button>
                                    </form>
                                    <span class="stepper__value">{{ $line['quantity'] }}</span>
                                    <form method="post" action="{{ route('basket.update', $medicine) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="quantity" value="{{ $line['quantity'] + 1 }}">
                                        <button class="stepper__button" type="submit" aria-label="One pack more" @disabled($line['quantity'] >= $max)>
                                            <svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 8h10M8 3v10"/></svg>
                                        </button>
                                    </form>
                                </div>
                                @if ($line['quantity'] >= $max)
                                    <p class="small muted">The most per order</p>
                                @endif
                            </div>
                            <p class="basket-line__price">{{ euros($line['line_cents']) }}</p>
                            <form method="post" action="{{ route('basket.destroy', $medicine) }}">
                                @csrf
                                @method('DELETE')
                                <button class="link-button" type="submit" aria-label="Remove {{ $medicine->name }}">Remove</button>
                            </form>
                        </li>
                    @endforeach
                </ul>

                <aside class="summary" aria-label="Order summary">
                    <h2>Summary</h2>
                    <dl class="totals">
                        <dt>Medicines</dt>
                        <dd>{{ euros($totalCents) }}</dd>
                        <dt>Collection at the pharmacy</dt>
                        <dd>Free</dd>
                        <dt class="totals__total">Total</dt>
                        <dd class="totals__total">{{ euros($totalCents) }}</dd>
                    </dl>
                    <a class="btn btn--primary btn--block" href="{{ route('checkout.show') }}">Go to checkout</a>
                    <p class="small muted">A pharmacist checks every order before we prepare it. If a medicine isn’t right for you, we call you.</p>
                    <a class="small-link" href="{{ route('shop.index') }}">Keep shopping</a>
                </aside>
            </div>
        @endif
    </main>

    @include('partials.footer')
@endsection
