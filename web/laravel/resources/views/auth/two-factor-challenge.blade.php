@extends('layouts.account')

@section('title', 'Enter your code · '.config('app.name'))

@section('header')
    @include('partials.auth-header', ['asideLink' => route('login'), 'asideText' => 'Log in with another account'])
@endsection

@section('main')
    <div class="auth-grid wrap">
        <section class="auth-card" aria-labelledby="code-title">
            <div class="auth-card__head">
                <p class="pill-label">Step 2 of 2</p>
                <h1 id="code-title">Enter your code</h1>
            </div>

            <div class="hint-box">
                <span class="hint-box__icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="7" y="2.5" width="14" height="23" rx="3"/><path d="M12 21.5h4" stroke-linecap="round"/><path d="M10.5 10.5l2.5 2.5 4.5-4.8" stroke-linecap="round"/></svg></span>
                @if ($recovery)
                    <p>Type one of the recovery codes you saved when you turned on 2-step verification: two groups of 10 letters and numbers. Each code works once.</p>
                @else
                    <p>Open your authenticator app and type the 6-digit code it shows for {{ config('app.name') }}. It changes every 30 seconds.</p>
                @endif
            </div>

            <form class="auth-form" method="post" action="{{ route('two-factor.login.store') }}">
                @csrf
                @if ($recovery)
                    <div class="field">
                        <label for="recovery_code">Recovery code</label>
                        <input class="input--code input--recovery" id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code" autocapitalize="off" spellcheck="false" maxlength="40" required @error('recovery_code') aria-invalid="true" aria-describedby="code-error" @enderror>
                        @error('recovery_code')<p class="field__error" id="code-error">{{ $message }}</p>@enderror
                    </div>
                @else
                    <div class="field">
                        <label for="code">6-digit code</label>
                        <input class="input--code" id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="6" required @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                        @error('code')<p class="field__error" id="code-error">{{ $message }}</p>@enderror
                    </div>
                @endif
                <button class="btn btn--primary btn--block" type="submit">Continue</button>
            </form>

            @if ($recovery)
                <a class="small-link" href="{{ route('two-factor.login') }}">Use the code from my app</a>
            @else
                <a class="small-link" href="{{ route('two-factor.login', ['recovery' => 1]) }}">Use a recovery code instead</a>
            @endif
        </section>

        <aside class="auth-side" aria-labelledby="help-title">
            <h2 id="help-title">Can’t get a code?</h2>
            <div class="help-item">
                <h3>The code is always wrong</h3>
                <p class="muted">Codes depend on your phone’s clock. Set the date and time to automatic, then try the newest code.</p>
            </div>
            <div class="help-item">
                <h3>New phone</h3>
                <p class="muted">Log in with a recovery code, then set up 2-step verification again in your account, under Security.</p>
            </div>
            <div class="help-item">
                <h3>Lost your phone and your recovery codes</h3>
                <p class="muted">Come to the pharmacy with your ID, or call us on <a href="tel:{{ config('pharmacy.phone.dial') }}">{{ config('pharmacy.phone.display') }}</a>. We check it’s you before turning 2-step verification off, and email you when we do.</p>
            </div>
        </aside>
    </div>
@endsection
