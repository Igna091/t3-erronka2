@extends('layouts.site')

@section('title', 'Order '.$order->reference.' · '.config('app.name'))

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/shop.css') }}">
@endpush

@section('content')
    @include('partials.demo-bar')
    @include('partials.header', ['shop' => true])

    <main id="main" class="shop-main">
        <section class="received wrap">
            <div class="received__main">
                <div class="received__title">
                    <svg width="56" height="56" viewBox="0 0 18 18" aria-hidden="true"><path d="M6 0h6v6h6v6h-6v6H6v-6H0V6h6z" fill="#1DB45A"/></svg>
                    <h1>Order received</h1>
                    <p class="lead">Order {{ $order->reference }}. We’ll text you at {{ $order->phone }} when it’s ready to collect.</p>
                </div>

                @if (in_array($order->payment_status, ['pending', 'declined'], true))
                    <div class="callout callout--action">
                        <p>The card payment isn’t finished.</p>
                        <div class="actions">
                            <a class="btn btn--primary btn--small" href="{{ route('payment.show', $order) }}">Pay by card</a>
                            <form method="post" action="{{ route('payment.counter', $order) }}">
                                @csrf
                                <button class="btn btn--outline btn--small" type="submit">Pay at the counter instead</button>
                            </form>
                        </div>
                    </div>
                @endif

                <ol class="progress-steps">
                    <li class="is-done">
                        <span class="progress-steps__mark" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5l3.2 3L13 4.5"/></svg>
                        </span>
                        <h2>Received</h2>
                        <p>Done, at {{ $order->created_at->setTimezone(config('pharmacy.timezone'))->format('G:i') }}.</p>
                    </li>
                    <li class="is-current">
                        <span class="progress-steps__mark" aria-hidden="true">2</span>
                        <h2>Pharmacist check</h2>
                        <p>In progress. Usually within [1 hour].</p>
                    </li>
                    <li>
                        <span class="progress-steps__mark" aria-hidden="true">3</span>
                        <h2>Ready to collect</h2>
                        <p>Next. We text you when it’s waiting at the counter.</p>
                    </li>
                </ol>

                <p class="muted">If the pharmacist decides a medicine isn’t right for the person taking it, we call you before preparing the order, and you don’t pay for it.</p>

                <div class="order-summary">
                    <div>
                        <h2>Collect at</h2>
                        <p>
                            {{ config('app.name') }}
                            @foreach (config('pharmacy.address') as $line)
                                <br>{{ $line }}
                            @endforeach
                        </p>
                    </div>
                    <div>
                        <h2>Medicines</h2>
                        <ul>
                            @foreach ($order->items as $item)
                                <li>{{ $item->name }} × {{ $item->quantity }}</li>
                            @endforeach
                        </ul>
                        <p class="order-summary__total">Total {{ euros($order->total_cents) }}</p>
                    </div>
                    <div>
                        <h2>Payment</h2>
                        @if ($order->payment_status === 'paid')
                            <p>Card ending {{ $order->card_last4 }} (simulated).</p>
                            <p class="small muted">Nothing was charged: it was a demo card.</p>
                        @elseif ($order->payment_status === 'at_counter')
                            <p>At the counter when you collect it, cash or card.</p>
                        @else
                            <p>Not paid yet.</p>
                        @endif
                    </div>
                </div>

                <div class="actions">
                    <a class="btn btn--outline" href="{{ route('shop.index') }}">Back to the shop</a>
                </div>
            </div>

            <x-capsule class="received__capsule" progress="1" />
        </section>
    </main>

    @include('partials.footer')
@endsection
