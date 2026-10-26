@extends('layouts.site')

@section('title', 'Card payment · '.config('app.name'))

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/shop.css') }}">
@endpush
@push('scripts')
    <script src="{{ versioned_asset('js/shop.js') }}" defer></script>
@endpush

@section('content')
    @include('partials.demo-bar')

    <header class="site-header site-header--slim wrap">
        <a class="brand" href="{{ route('home') }}">
            <svg width="34" height="18" viewBox="0 0 34 18" aria-hidden="true"><rect x="1" y="2" width="20" height="14" rx="7" fill="#F2C94C"/><path d="M15 1h10a8 8 0 0 1 0 16H15z" fill="#2A4BD7"/></svg>
            {{ config('app.name') }}
        </a>
        <p class="secure">
            <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="3" y="7" width="10" height="7" rx="2"/><path d="M5 7V5a3 3 0 0 1 6 0v2"/></svg>
            Secure payment (simulated)
        </p>
    </header>

    <main id="main" class="shop-main">
        <section class="page-title wrap">
            <h1>Card payment</h1>
            <p class="muted">It works like a real card payment, but it’s simulated: nothing is charged.</p>
        </section>

        <div class="with-summary wrap">
            <div class="pay">
                {{-- The card picture. On the form step shop.js fills it in as you type; on the other
                     steps it shows the card's last 4 digits and the result. --}}
                <div class="card-stage">
                    <div class="card" data-card data-state="{{ $step }}" aria-hidden="true">
                        <div class="card__flipper">
                            <div class="card__face card__front">
                                <span class="card__sheen"></span>
                                <span class="card__chip"><i></i><i></i><i></i></span>
                                <svg class="card__waves" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 7a7 7 0 0 1 0 10"/><path d="M12 4a11 11 0 0 1 0 16"/><path d="M16 1.5a14 14 0 0 1 0 21"/></svg>
                                <svg class="card__logo" viewBox="0 0 34 18"><rect x="1" y="2" width="20" height="14" rx="7" fill="#F2C94C"/><path d="M15 1h10a8 8 0 0 1 0 16H15z" fill="#2A4BD7"/></svg>
                                <span class="card__number" data-card-number>
                                    @for ($group = 0; $group < 4; $group++)
                                        <span class="card__group">
                                            @for ($slot = 0; $slot < 4; $slot++)
                                                @if ($group === 3 && $last4)
                                                    <span class="card__slot">{{ $last4[$slot] }}</span>
                                                @else
                                                    <span class="card__slot is-empty">•</span>
                                                @endif
                                            @endfor
                                        </span>
                                    @endfor
                                </span>
                                <span class="card__holder">
                                    <span class="card__label">Card holder</span>
                                    <span class="card__value" data-card-name><span class="is-empty">{{ $step === 'form' ? 'YOUR NAME' : '•••••• ••••' }}</span></span>
                                </span>
                                <span class="card__expiry">
                                    <span class="card__label">Expires</span>
                                    <span class="card__value" data-card-expiry>
                                        @foreach (mb_str_split($step === 'form' ? 'MM/YY' : '••/••') as $placeholder)
                                            <span @class(['card__slot', 'is-empty' => $placeholder !== '/'])>{{ $placeholder }}</span>
                                        @endforeach
                                    </span>
                                </span>
                                <span class="card__outline"></span>
                                @if ($step === 'approved')
                                    <span class="card__stamp card__stamp--approved"><span>Approved</span></span>
                                @elseif ($step === 'declined')
                                    <span class="card__stamp card__stamp--declined"><span>Declined</span></span>
                                @endif
                            </div>
                            <div class="card__face card__back">
                                <span class="card__stripe"></span>
                                <span class="card__label card__label--cvc">Security code</span>
                                <span class="card__signature">
                                    <span class="card__strip"></span>
                                    <span class="card__cvc" data-card-cvc><span class="card__slot is-empty">•</span><span class="card__slot is-empty">•</span><span class="card__slot is-empty">•</span></span>
                                </span>
                                <span class="card__note">Only the demo cards are accepted here.</span>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($step === 'form')
                    <form class="pay-form" method="post" action="{{ route('payment.store', $order) }}" autocomplete="off" data-pay-form>
                        @csrf
                        <div class="field">
                            <label for="card_number">Card number</label>
                            <input id="card_number" name="card_number" type="text" inputmode="numeric" placeholder="1234 1234 1234 1234" maxlength="19" autocomplete="off" required data-card-input="number" @error('card_number') aria-invalid="true" aria-describedby="card_number-error" @enderror>
                            @error('card_number')<p class="field__error" id="card_number-error">{{ $message }}</p>@enderror
                            <p class="demo-cards" data-demo-cards hidden>
                                Demo cards:
                                <button class="link-button" type="button" data-demo-card="4242424242424242">one that’s approved</button>
                                <span aria-hidden="true">·</span>
                                <button class="link-button" type="button" data-demo-card="4000000000000002">one that’s declined</button>
                            </p>
                            <noscript><p class="field__help">Demo cards: 4242 4242 4242 4242 is approved and 4000 0000 0000 0002 is declined.</p></noscript>
                        </div>
                        <div class="field">
                            <label for="card_name">Name on the card</label>
                            <input id="card_name" name="card_name" type="text" value="{{ old('card_name') }}" maxlength="60" autocomplete="off" required data-card-input="name" @error('card_name') aria-invalid="true" aria-describedby="card_name-error" @enderror>
                            @error('card_name')<p class="field__error" id="card_name-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="fields">
                            <div class="field">
                                <label for="card_expiry">Expiry date</label>
                                <input id="card_expiry" name="card_expiry" type="text" inputmode="numeric" placeholder="MM/YY" maxlength="5" autocomplete="off" required data-card-input="expiry" @error('card_expiry') aria-invalid="true" aria-describedby="card_expiry-error" @enderror>
                                @error('card_expiry')<p class="field__error" id="card_expiry-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="field">
                                <label for="card_cvc">Security code</label>
                                <input id="card_cvc" name="card_cvc" type="text" inputmode="numeric" placeholder="123" maxlength="3" autocomplete="off" required data-card-input="cvc" aria-describedby="card_cvc-help @error('card_cvc') card_cvc-error @enderror" @error('card_cvc') aria-invalid="true" @enderror>
                                <p class="field__help" id="card_cvc-help">The 3 digits on the back</p>
                                @error('card_cvc')<p class="field__error" id="card_cvc-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <button class="btn btn--primary btn--block btn--pay" type="submit" data-busy-button>
                            <span class="btn__progress" aria-hidden="true"></span>
                            <span class="btn__label" data-busy-label="Contacting your bank (simulated)">Pay {{ euros($order->total_cents) }}</span>
                        </button>
                        <p class="secure-note">
                            <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="3" y="7" width="10" height="7" rx="2"/><path d="M5 7V5a3 3 0 0 1 6 0v2"/></svg>
                            Card details are never stored or sent to a bank.
                        </p>
                    </form>
                    <form class="pay-alternative" method="post" action="{{ route('payment.counter', $order) }}">
                        @csrf
                        <button class="link-button" type="submit">Pay at the counter instead</button>
                    </form>
                @elseif ($step === 'bank')
                    <div class="bank" data-bank>
                        <div class="phone" role="img" aria-label="Your phone shows a text message from your bank with the code {{ $code }}">
                            <span class="phone__ring"></span>
                            <div class="phone__body">
                                <span class="phone__speaker"></span>
                                <div class="phone__message">
                                    <p class="phone__from">Your bank</p>
                                    <p data-message-code>Code <strong>{{ $code }}</strong> confirms your {{ euros($order->total_cents) }} payment to {{ config('app.name') }}. Never share it.</p>
                                    <p data-message-done hidden>Payment confirmed.</p>
                                </div>
                            </div>
                        </div>
                        <form class="bank__form" method="post" action="{{ route('payment.confirm', $order) }}" autocomplete="off" data-confirm-form>
                            @csrf
                            <p class="pill-label">Your bank’s check (simulated)</p>
                            <h2>Confirm it’s you</h2>
                            <p>Your bank sent a code to your phone to confirm this payment.</p>
                            <div class="field">
                                <label for="code">Code from the text message</label>
                                <input class="input--code" id="code" name="code" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                                @error('code')<p class="field__error" id="code-error">{{ $message }}</p>@enderror
                            </div>
                            <button class="btn btn--primary btn--pay" type="submit" data-busy-button>
                                <span class="btn__progress" aria-hidden="true"></span>
                                <span class="btn__label" data-busy-label="Confirming">Confirm payment</span>
                            </button>
                        </form>
                    </div>
                @elseif ($step === 'approved')
                    <div class="result" role="status">
                        <div class="burst" aria-hidden="true">
                            @for ($i = 0; $i < 12; $i++)
                                <span class="burst__dot burst__dot--{{ $i }}"></span>
                            @endfor
                            <span class="burst__circle"></span>
                            <svg class="burst__check" viewBox="0 0 120 120"><path d="M38 62l15 15 30-32"/></svg>
                        </div>
                        <h2>Payment approved</h2>
                        <p>Card ending {{ $last4 }} · {{ euros($order->total_cents) }}</p>
                        <p class="muted">Simulated: nothing was charged and the card details weren’t kept.</p>
                        <a class="btn btn--primary" href="{{ route('orders.show', $order) }}">See your order</a>
                    </div>
                @else
                    <div class="result" role="alert">
                        <span class="result__icon" aria-hidden="true">
                            <svg width="40" height="40" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                        </span>
                        <h2>Payment declined</h2>
                        <p class="result__text">
                            @if ($reason === 'code')
                                The code was wrong three times, so the bank stopped the payment.
                            @else
                                This demo card is always declined, so you can see what happens when a bank says no.
                            @endif
                            Your order hasn’t been paid.
                        </p>
                        <div class="actions actions--center">
                            <a class="btn btn--primary" href="{{ route('payment.show', $order) }}">Try another card</a>
                            <form method="post" action="{{ route('payment.counter', $order) }}">
                                @csrf
                                <button class="btn btn--outline" type="submit">Pay at the counter instead</button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>

            <aside class="summary" aria-label="Order summary">
                <h2>Paying for</h2>
                <ul class="summary__lines">
                    @foreach ($order->items as $item)
                        <li><span>{{ $item->name }} <span class="muted">× {{ $item->quantity }}</span></span><span>{{ euros($item->unit_price_cents * $item->quantity) }}</span></li>
                    @endforeach
                </ul>
                <dl class="totals">
                    <dt>Collection at the pharmacy</dt>
                    <dd>Free</dd>
                    <dt class="totals__total">Total</dt>
                    <dd class="totals__total">{{ euros($order->total_cents) }}</dd>
                </dl>
                <p class="small muted">Order {{ $order->reference }}. Collect it at {{ config('app.name') }}, {{ config('pharmacy.address')[0] }}. We text you when it’s ready.</p>
            </aside>
        </div>
    </main>

    @include('partials.footer')
@endsection
