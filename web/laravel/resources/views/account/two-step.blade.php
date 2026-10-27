@extends('layouts.account')

@section('title', 'Turn on 2-step verification · '.config('app.name'))

@section('header')
    @include('partials.header')
@endsection

@section('main')
    <div class="auth-single auth-single--wide wrap">
        <section class="auth-card" aria-labelledby="step-title">
            @include('partials.account-status')

            @if (in_array($step, ['scan', 'codes'], true))
                <ol class="setup-steps" aria-label="Setting up 2-step verification">
                    @foreach (['Add to your app', 'Enter the code', 'Save recovery codes'] as $index => $label)
                        @php($number = $index + 1)
                        @php($at = $step === 'scan' ? ($errors->has('code') || old('code') ? 2 : 1) : 3)
                        <li @class(['is-done' => $number < $at, 'is-current' => $number === $at]) @if ($number === $at) aria-current="step" @endif>
                            <span class="setup-steps__mark" aria-hidden="true">{{ $number < $at ? '✓' : $number }}</span>
                            {{ $label }}
                        </li>
                    @endforeach
                </ol>
            @endif

            @if ($step === 'intro')
                <div class="auth-card__head">
                    <p class="pill-label pill-label--ok">Recommended</p>
                    <h1 id="step-title">Protect your account with 2-step verification</h1>
                    <p class="muted">Your account will hold prescriptions, which are health data. With 2-step verification on, logging in takes your password and a code from an app on your phone, so a stolen password alone gets nobody in.</p>
                </div>
                <div class="fact-grid">
                    <div><h2>What you need</h2><p class="muted">A free authenticator app on your phone, such as Google Authenticator, Microsoft Authenticator, Aegis or 2FAS.</p></div>
                    <div><h2>How long</h2><p class="muted">About 2 minutes, once.</p></div>
                    <div><h2>After that</h2><p class="muted">Each time you log in, you type the code your app shows after your password.</p></div>
                </div>
                <div class="actions">
                    @if ($confirmed)
                        <form method="post" action="{{ route('account.two-step.start') }}">
                            @csrf
                            <button class="btn btn--primary" type="submit">Turn it on</button>
                        </form>
                    @else
                        <a class="btn btn--primary" href="{{ route('account.two-step', ['step' => 'password']) }}">Turn it on</a>
                    @endif
                    <a class="small-link" href="{{ route('account.dashboard') }}">Not now</a>
                </div>
                <p class="field__help">You can turn it on later in your account, under Security.</p>
            @elseif ($step === 'password')
                <div class="auth-card__head">
                    <h1 id="step-title">Confirm it’s you</h1>
                    <p class="muted">Enter your password to change your security settings.</p>
                </div>
                <form class="auth-form" method="post" action="{{ route('account.two-step.start') }}">
                    @csrf
                    <div class="field">
                        <label for="password">Password</label>
                        @include('partials.password-input', ['id' => 'password', 'name' => 'password', 'autocomplete' => 'current-password', 'error' => $errors->has('password')])
                        @error('password')<p class="field__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="actions">
                        <button class="btn btn--primary" type="submit">Continue</button>
                        <a class="small-link" href="{{ route('account.two-step') }}">Back</a>
                    </div>
                </form>
            @elseif ($step === 'scan')
                <h1 id="step-title">Add your account to the app</h1>
                <div class="scan">
                    <figure class="scan__qr">
                        {!! $qr !!}
                        <figcaption>Scan it with the app</figcaption>
                    </figure>
                    <div class="scan__how">
                        <ol>
                            <li>Open your authenticator app and tap <strong>+</strong> or <strong>Add account</strong>.</li>
                            <li>Scan the QR code. On this phone? Use the key below instead.</li>
                            <li>The app shows <strong>{{ config('app.name') }}</strong> ({{ auth()->user()->email }}) with a 6-digit code.</li>
                        </ol>
                        <div class="key-box">
                            <h2>Can’t scan it?</h2>
                            <p class="muted">Choose to type a key in the app, pick a time-based code, and type this:</p>
                            <div class="key-box__row">
                                <code id="setup-key">{{ $key }}</code>
                                <button class="btn btn--outline btn--small" type="button" data-copy="setup-key" hidden>Copy key</button>
                            </div>
                        </div>
                    </div>
                </div>
                <form class="auth-form auth-form--narrow" method="post" action="{{ route('account.two-step.confirm') }}">
                    @csrf
                    <div class="field">
                        <label for="code">Then type the 6-digit code it shows</label>
                        <input class="input--code" id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="6" required @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                        @error('code')<p class="field__error" id="code-error">{{ $message }}</p>@enderror
                    </div>
                    <button class="btn btn--primary" type="submit">Check the code</button>
                </form>
                <form method="post" action="{{ route('account.two-step.cancel') }}">
                    @csrf
                    @method('delete')
                    <button class="link-button" type="submit">Cancel</button>
                </form>
            @elseif ($step === 'codes')
                <div class="auth-card__head">
                    <h1 id="step-title">Save your recovery codes</h1>
                    <p class="muted">If you lose your phone, each code lets you log in once instead of the app. Keep them somewhere safe, such as a password manager or printed at home. Anyone who has them can get past 2-step verification, so don’t email them to yourself.</p>
                </div>
                @include('account.partials.recovery-codes', ['codes' => $codes])
                <form class="auth-form" method="post" action="{{ route('account.two-step.finish') }}">
                    @csrf
                    <div class="field">
                        <div class="consent">
                            <input id="saved" name="saved" type="checkbox" value="1" required @error('saved') aria-invalid="true" aria-describedby="saved-error" @enderror>
                            <label for="saved">I’ve saved my recovery codes somewhere safe</label>
                        </div>
                        @error('saved')<p class="field__error" id="saved-error">{{ $message }}</p>@enderror
                    </div>
                    <button class="btn btn--primary" type="submit">Finish</button>
                </form>
            @else
                <div class="done" role="status">
                    <span class="big-icon big-icon--green" aria-hidden="true"><svg width="40" height="40" viewBox="0 0 36 36" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18.5l6 6 12-13"/></svg></span>
                    <h1 id="step-title">2-step verification is on</h1>
                    <p class="muted">From now on, we ask for a code from your app after your password. We’ve emailed {{ auth()->user()->email }} to let you know, so you’d notice if it wasn’t you.</p>
                    <a class="btn btn--primary" href="{{ route('account.security') }}">Back to Security</a>
                </div>
            @endif
        </section>
    </div>
@endsection
