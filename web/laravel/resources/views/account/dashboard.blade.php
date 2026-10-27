@extends('layouts.account')

@section('title', 'Your account · '.config('app.name'))

@section('header')
    @include('partials.header')
@endsection

@section('main')
    <div class="account-layout wrap">
        @include('partials.account-nav')

        <div class="account-content">
            @include('partials.account-status')
            <div class="account-title">
                <h1>Hello, {{ $user->firstName() }}</h1>
                <p class="muted">Your orders, your details and your account’s security, in one place.</p>
            </div>

            <section class="send-card" aria-labelledby="send-title">
                <div>
                    <h2 id="send-title">Send a prescription</h2>
                    <p>Sending a photo of a prescription from your account comes next. Until then, bring the paper one to the counter, or call us with your health card number for an e-prescription.</p>
                </div>
                <a class="btn send-card__button" href="tel:{{ config('pharmacy.phone.dial') }}">
                    <svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M4 2.5h3l1.5 4-2 1.5a10 10 0 0 0 5.5 5.5l1.5-2 4 1.5v3a1.5 1.5 0 0 1-1.5 1.5A15 15 0 0 1 2.5 4 1.5 1.5 0 0 1 4 2.5z"/></svg>
                    Call {{ config('pharmacy.phone.display') }}
                </a>
            </section>

            @unless ($user->profile_image)
                <section class="nudge nudge--photo" aria-labelledby="photo-title">
                    <span class="nudge__icon" aria-hidden="true"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4.2 4.2-6.5 8-6.5s7 2.3 8 6.5"/></svg></span>
                    <div>
                        <h2 id="photo-title">Add a profile photo</h2>
                        <p>So we recognise you at the counter when you collect a prescription. Only the pharmacy team sees it.</p>
                    </div>
                    <a class="btn btn--outline btn--small" href="{{ route('account.profile') }}">Add a photo</a>
                </section>
            @endunless

            <div class="account-grid">
                <section class="panel-card" aria-labelledby="orders-title">
                    <div class="panel-card__head">
                        <h2 id="orders-title">Shop orders</h2>
                        <a class="small-link" href="{{ route('shop.index') }}">Go to the shop</a>
                    </div>
                    @forelse ($orders as $order)
                        <a class="order-row" href="{{ route('orders.show', $order) }}">
                            <span class="order-row__top">
                                <strong>{{ $order->reference }}</strong>
                                <span>{{ euros($order->total_cents) }}</span>
                            </span>
                            <span class="muted">{{ $order->items->map(fn ($item) => $item->name.' × '.$item->quantity)->implode(', ') }}</span>
                            <span @class(['status-pill', 'status-pill--ok' => in_array($order->payment_status, ['paid', 'at_counter'], true)])>
                                {{ ['paid' => 'Paid by card', 'at_counter' => 'Pay at the counter', 'pending' => 'Card payment not finished', 'declined' => 'Card declined'][$order->payment_status] ?? $order->payment_status }}
                            </span>
                        </a>
                    @empty
                        <p class="muted">No orders yet. What you buy in the shop while logged in shows up here.</p>
                    @endforelse
                </section>

                <div class="account-stack">
                    <section class="panel-card" aria-labelledby="rx-title">
                        <h2 id="rx-title">Prescriptions</h2>
                        <p class="muted">No prescriptions yet. When sending them from here is ready, you’ll follow each one: received, checked by a pharmacist, ready to collect.</p>
                    </section>
                    <section class="panel-card" aria-labelledby="pack-title">
                        <h2 id="pack-title">Dose packs</h2>
                        <p class="muted">A week of tablets, sorted by day and time of day.</p>
                        <a class="small-link" href="{{ route('home') }}#dose-packs">Ask about dose packs</a>
                    </section>
                </div>
            </div>

            @if ($user->hasTwoStep())
                <p class="secure-note">
                    <svg width="20" height="20" viewBox="0 0 18 18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M9 1.5l6 2.5v4.5c0 3.8-2.6 6.6-6 8-3.4-1.4-6-4.2-6-8V4z"/><path d="M6.2 9l2 2 3.6-3.8" stroke-linecap="round"/></svg>
                    2-step verification is on. <a href="{{ route('account.security') }}">Security settings</a>
                </p>
            @else
                <section class="nudge nudge--secure" aria-labelledby="secure-title">
                    <span class="nudge__icon" aria-hidden="true"><svg width="36" height="36" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="M9 1.5l6 2.5v4.5c0 3.8-2.6 6.6-6 8-3.4-1.4-6-4.2-6-8V4z"/><path d="M9 6v3.5M9 12v0.01" stroke-linecap="round"/></svg></span>
                    <div>
                        <h2 id="secure-title">Turn on 2-step verification</h2>
                        <p class="muted">Your account will hold prescriptions, which are health data. With a code from your phone as well as your password, a stolen password alone gets nobody in.</p>
                    </div>
                    <a class="btn btn--primary btn--small" href="{{ route('account.two-step') }}">Turn it on</a>
                </section>
            @endif
        </div>
    </div>
@endsection
