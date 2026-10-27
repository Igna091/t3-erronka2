@extends('layouts.site')

@section('description', 'Send your prescription online and pick it up at the counter without waiting in line.')

@section('content')
    @include('partials.header')

    @auth
        <div class="wrap">
            <section class="welcome-strip" aria-label="Your account">
                <p>
                    <span class="welcome-strip__dot" aria-hidden="true"></span>
                    <span>
                        <strong>Hello, {{ auth()->user()->firstName() }}.</strong>
                        @if (! auth()->user()->hasVerifiedEmail())
                            Confirm your email to finish opening your account.
                        @elseif ($latestOrder && $latestOrder->created_at?->isAfter(now()->subDays(14)))
                            Your order {{ $latestOrder->reference }} is being prepared. We text you when it’s ready to collect.
                        @elseif (! auth()->user()->hasTwoStep())
                            Protect your account with 2-step verification.
                        @else
                            Good to see you again.
                        @endif
                    </span>
                </p>
                <a href="{{ route('account.dashboard') }}">Go to your account</a>
            </section>
        </div>
    @endauth

    <main id="main">
        {{-- Wide screens: the text scrolls on the left while one capsule stays pinned on the right and
             reaches each data-capsule-step as that block passes the middle of the window. Narrower
             screens show the inline capsules instead. --}}
        <div class="story wrap">
            <div class="story-text">
                <section id="top" class="panel panel--hero" data-capsule-step="0">
                    <x-capsule class="capsule-inline" progress="0" />
                    <h1>Send it now.<br>Pick it up on your way home.</h1>
                    <p class="lead">Upload a photo of your prescription or give us your health card number. A pharmacist gets it ready and texts you when it’s waiting at the counter.</p>
                    <div class="actions">
                        {{-- Sending a prescription needs an account --}}
                        <a class="btn btn--primary" href="{{ auth()->check() ? route('account.dashboard') : route('login') }}">Send a prescription</a>
                        <a class="btn btn--outline" href="tel:{{ config('pharmacy.phone.dial') }}">Call {{ config('pharmacy.phone.display') }}</a>
                    </div>
                </section>

                <section id="refills" class="panel panel--refills" data-capsule-step="0.25">
                    <h2>How a refill works</h2>
                    <ol class="steps">
                        <li>
                            <span class="step-number" aria-hidden="true">1</span>
                            <div>
                                <h3>Send your prescription</h3>
                                <p>Upload a photo of the paper one, or give us your health card number for an e‑prescription.</p>
                            </div>
                        </li>
                        <li>
                            <span class="step-number" aria-hidden="true">2</span>
                            <div>
                                <h3>We get it ready</h3>
                                <p>A pharmacist checks it against the other medicines you take. If something is out of stock, we call you before ordering it.</p>
                            </div>
                        </li>
                        <li>
                            <span class="step-number" aria-hidden="true">3</span>
                            <div>
                                <h3>Collect it, or we bring it</h3>
                                <p>Pick it up at the counter without waiting in line, or ask for home delivery.</p>
                            </div>
                        </li>
                    </ol>
                </section>

                <section id="services" class="panel panel--services">
                    <x-capsule class="capsule-inline" progress="0.62" />
                    <div class="panel-intro" data-capsule-step="0.5">
                        <h2>What’s inside</h2>
                        <p class="muted">Things you can ask for at the counter, beyond picking up a prescription.</p>
                    </div>
                    <ul class="services" data-capsule-step="0.75">
                        <li>
                            <span class="dot dot--blue" aria-hidden="true"></span>
                            <div>
                                <h3>Medication review</h3>
                                <p>Bring everything you take in one bag. We go through it with you and flag anything that shouldn’t be mixed.</p>
                            </div>
                        </li>
                        <li>
                            <span class="dot dot--yellow" aria-hidden="true"></span>
                            <div>
                                <h3>Blood pressure and glucose checks</h3>
                                <p>Quick readings at the counter, written down so you can show them to your doctor.</p>
                            </div>
                        </li>
                        <li>
                            <span class="dot dot--white" aria-hidden="true"></span>
                            <div>
                                <h3>Advice on everyday things</h3>
                                <p>Rashes, bites, a cold that won’t clear, a baby who won’t settle. Ask us first and we’ll tell you if it needs a doctor.</p>
                            </div>
                        </li>
                        <li>
                            <span class="dot dot--yellow" aria-hidden="true"></span>
                            <div>
                                <h3>Home delivery</h3>
                                <p>For anyone who can’t easily get out. Ask by phone or at the counter.</p>
                            </div>
                        </li>
                    </ul>
                </section>

                <section id="dose-packs" class="panel panel--dose" data-capsule-step="1">
                    <x-capsule class="capsule-inline" progress="1" />
                    <h2>A week of tablets, sorted for you</h2>
                    <p class="muted">If you take several medicines, we can pack them by day and time of day in sealed blister packs. You open one pocket, take what’s inside, and stop wondering whether you already had this morning’s.</p>
                    <p class="muted">Bring your prescriptions and talk to a pharmacist. We’ll tell you whether a dose pack suits you.</p>
                    <div class="actions actions--spaced">
                        <a class="btn btn--outline" href="#visit">Ask about dose packs</a>
                    </div>
                </section>
            </div>

            <div class="story-visual">
                <x-capsule class="capsule-pinned" />
            </div>
        </div>

        <section id="visit" class="visit">
            <div class="visit-inner wrap">
                <div class="visit-col">
                    <h2>Visit us</h2>
                    <dl class="facts">
                        <dt>Address</dt>
                        <dd>
                            @foreach (config('pharmacy.address') as $line)
                                {{ $line }}@if (! $loop->last)<br>@endif
                            @endforeach
                        </dd>
                        <dt>Phone</dt>
                        <dd><a href="tel:{{ config('pharmacy.phone.dial') }}">{{ config('pharmacy.phone.display') }}</a></dd>
                        @foreach ($hours as [$days, $times])
                            <dt>{{ $days }}</dt>
                            <dd>{{ $times }}</dd>
                        @endforeach
                    </dl>
                    <p class="muted">When we’re closed, another pharmacy nearby is on duty. <a href="{{ config('pharmacy.on_duty_url') }}">See tonight’s on-duty pharmacy</a></p>
                </div>

                <div class="visit-col visit-col--now">
                    <div @class(['now', 'is-closed' => ! $status['open']])>
                        <svg class="cross" width="88" height="88" viewBox="0 0 18 18" aria-hidden="true"><path d="M6 0h6v6h6v6h-6v6H6v-6H0V6h6z" fill="currentColor"/></svg>
                        <div>
                            <p class="now-title">{{ $status['open'] ? 'Open now' : 'Closed now' }}</p>
                            @if ($status['open'])
                                <p class="now-detail">Until {{ $status['closes'] }} today</p>
                            @elseif ($status['opens'])
                                <p class="now-detail">Opens {{ $status['opens'] }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="map">Map of {{ config('pharmacy.address')[0] }}</div>
                </div>
            </div>
        </section>
    </main>

    @include('partials.footer')
@endsection
