@extends('layouts.site')

@section('title', 'Checkout · '.config('app.name'))

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
        <section class="page-title wrap">
            <h1>Checkout</h1>
            <p class="muted">A pharmacist reads your answers before we prepare the order.</p>
        </section>

        <form class="with-summary wrap" method="post" action="{{ route('checkout.store') }}" data-checkout>
            @csrf
            <div class="checkout">
                @if ($errors->any())
                    <div class="form-errors" role="alert">
                        <p>Check these and try again:</p>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <section class="step" aria-labelledby="details-title">
                    <div class="step__head">
                        <span class="step-number" aria-hidden="true">1</span>
                        <h2 id="details-title">Your details</h2>
                    </div>
                    <div class="fields">
                        <div class="field field--wide">
                            <label for="name">Full name</label>
                            <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="100" required @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                            @error('name')<p class="field__error" id="name-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                            @error('email')<p class="field__error" id="email-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="phone">Mobile phone</label>
                            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" maxlength="20" required aria-describedby="phone-help @error('phone') phone-error @enderror" @error('phone') aria-invalid="true" @enderror>
                            <p class="field__help" id="phone-help">We text you when your order is ready.</p>
                            @error('phone')<p class="field__error" id="phone-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section class="step" aria-labelledby="questions-title">
                    <div class="step__head">
                        <span class="step-number" aria-hidden="true">2</span>
                        <h2 id="questions-title">Questions from the pharmacist</h2>
                    </div>
                    <p class="muted">So the pharmacist can check these medicines are right for the person who will take them.</p>

                    <fieldset class="choice">
                        <legend>Who will take them?</legend>
                        <div class="pills">
                            <label class="pill"><input type="radio" name="taker" value="me" @checked(old('taker', 'me') === 'me') required> Me</label>
                            <label class="pill"><input type="radio" name="taker" value="other" @checked(old('taker') === 'other')> Someone else</label>
                        </div>
                    </fieldset>

                    <div class="field">
                        <label for="age">Age of the person taking them</label>
                        <input class="input--short" id="age" name="age" type="number" inputmode="numeric" min="0" max="120" value="{{ old('age') }}" required @error('age') aria-invalid="true" aria-describedby="age-error" @enderror>
                        @error('age')<p class="field__error" id="age-error">{{ $message }}</p>@enderror
                    </div>

                    <fieldset class="choice">
                        <legend>Pregnant or breastfeeding?</legend>
                        <div class="pills">
                            <label class="pill"><input type="radio" name="pregnant" value="no" @checked(old('pregnant') === 'no') required> No</label>
                            <label class="pill"><input type="radio" name="pregnant" value="yes" @checked(old('pregnant') === 'yes')> Yes</label>
                            <label class="pill"><input type="radio" name="pregnant" value="na" @checked(old('pregnant') === 'na')> Doesn’t apply</label>
                        </div>
                        @error('pregnant')<p class="field__error">{{ $message }}</p>@enderror
                    </fieldset>

                    <div class="field">
                        <label for="health_notes">Other medicines they take, or health problems <span class="muted">(optional)</span></label>
                        <textarea id="health_notes" name="health_notes" rows="3" maxlength="1000">{{ old('health_notes') }}</textarea>
                    </div>

                    <div class="field">
                        <label for="allergies">Allergies to medicines <span class="muted">(optional)</span></label>
                        <input id="allergies" name="allergies" type="text" value="{{ old('allergies') }}" maxlength="255">
                    </div>

                    <div class="consent">
                        <input id="consent" name="consent" type="checkbox" value="1" @checked(old('consent')) required>
                        <label for="consent">I agree that the pharmacist uses these answers to check this order. They are kept with the order for {{ config('shop.health_data_kept') }} and never used for anything else. <a href="#">How we use health data</a></label>
                    </div>
                    @error('consent')<p class="field__error">{{ $message }}</p>@enderror
                </section>

                <section class="step" aria-labelledby="collection-title">
                    <div class="step__head">
                        <span class="step-number" aria-hidden="true">3</span>
                        <h2 id="collection-title">Collection</h2>
                    </div>
                    <div class="collection">
                        <svg width="22" height="22" viewBox="0 0 18 18" aria-hidden="true"><path d="M6 0h6v6h6v6h-6v6H6v-6H0V6h6z" fill="#1DB45A"/></svg>
                        <div>
                            <p class="collection__title">Collect it at {{ config('app.name') }}</p>
                            <p class="muted">{{ implode(', ', config('pharmacy.address')) }}. Free, usually ready in {{ config('shop.ready_within') }}. We text you when it’s waiting at the counter.</p>
                        </div>
                    </div>
                    <p class="muted">We don’t deliver: online orders are collected at the pharmacy.</p>
                </section>

                <section class="step" aria-labelledby="payment-title">
                    <div class="step__head">
                        <span class="step-number" aria-hidden="true">4</span>
                        <h2 id="payment-title">Payment</h2>
                    </div>
                    <p class="callout">This is a demo: card payments use demo cards and nothing is charged.</p>
                    <fieldset class="choice">
                        <legend>How do you want to pay?</legend>
                        <div class="options">
                            <label class="option">
                                <input type="radio" name="payment" value="card" @checked(old('payment', 'card') === 'card') required>
                                <span>
                                    <span class="option__title">Card (simulated)</span>
                                    <span class="option__text">Next you see a simulated card payment that takes demo cards.</span>
                                </span>
                            </label>
                            <label class="option">
                                <input type="radio" name="payment" value="counter" @checked(old('payment') === 'counter')>
                                <span>
                                    <span class="option__title">Pay at the counter</span>
                                    <span class="option__text">Cash or card, when you collect it.</span>
                                </span>
                            </label>
                        </div>
                    </fieldset>
                </section>
            </div>

            <aside class="summary" aria-label="Your order">
                <h2>Your order</h2>
                <ul class="summary__lines">
                    @foreach ($lines as $line)
                        <li><span>{{ $line['medicine']->name }} <span class="muted">× {{ $line['quantity'] }}</span></span><span>{{ euros($line['line_cents']) }}</span></li>
                    @endforeach
                </ul>
                <dl class="totals">
                    <dt>Medicines</dt>
                    <dd>{{ euros($totalCents) }}</dd>
                    <dt>Collection at the pharmacy</dt>
                    <dd>Free</dd>
                    <dt class="totals__total">Total</dt>
                    <dd class="totals__total">{{ euros($totalCents) }}</dd>
                </dl>
                <button class="btn btn--primary btn--block" type="submit" data-checkout-submit data-label-card="Continue to card payment" data-label-counter="Place order (simulation)">{{ old('payment') === 'counter' ? 'Place order (simulation)' : 'Continue to card payment' }}</button>
                <p class="small muted" data-consent-hint hidden>Tick the box at the end of step 2 first.</p>
                <p class="small muted">By placing the order you accept the <a href="#">terms of sale</a>.</p>
                <a class="small-link" href="{{ route('basket.show') }}">Back to the basket</a>
            </aside>
        </form>
    </main>

    @include('partials.footer')
@endsection
