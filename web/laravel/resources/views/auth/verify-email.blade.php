@extends('layouts.account')

@section('title', 'Check your inbox · '.config('app.name'))

@section('header')
    @include('partials.auth-header', ['asidePrefix' => 'Questions?', 'asideLink' => 'tel:'.config('pharmacy.phone.dial'), 'asideText' => 'Call '.config('pharmacy.phone.display')])
@endsection

@section('main')
    <div class="auth-single wrap">
        <section class="auth-card" aria-labelledby="inbox-title">
            <span class="big-icon big-icon--yellow" aria-hidden="true"><svg width="40" height="40" viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"><rect x="5" y="9" width="30" height="22" rx="4"/><path d="M6 11l14 11 14-11" stroke-linecap="round"/></svg></span>
            <div class="auth-card__head">
                <h1 id="inbox-title">Check your inbox</h1>
                <p class="lead-small">We sent a link to <strong>{{ auth()->user()->email }}</strong>. Open it on any device to confirm your email. It works once, for 60 minutes.</p>
            </div>
            <ul class="dot-list muted">
                <li>Not there after a few minutes? Look in spam or promotions.</li>
                <li>It comes from {{ config('app.name') }}, and never asks for your password.</li>
            </ul>

            @if (session('status') === 'verification-link-sent')
                <p class="form-note form-note--done" role="status">Sent a new link. The old one doesn’t work any more.</p>
            @endif

            <div class="actions">
                <form method="post" action="{{ route('verification.send') }}">
                    @csrf
                    <button class="btn btn--outline" type="submit">Send it again</button>
                </form>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="link-button" type="submit">Wrong address? Log out and start again</button>
                </form>
            </div>

            <div class="auth-card__foot">
                <p class="muted">Confirmed it in another tab?</p>
                <a class="btn btn--primary" href="{{ route('account.dashboard') }}">Continue</a>
            </div>
        </section>
    </div>
@endsection
