@extends('layouts.account')

@section('title', 'Log in · '.config('app.name'))

@section('header')
    @include('partials.auth-header', ['asideLink' => route('shop.index'), 'asideText' => 'Back to the shop'])
@endsection

@section('main')
    <div class="auth-grid wrap">
        <section class="auth-card" aria-labelledby="login-title">
            <div class="auth-card__head">
                <h1 id="login-title">Log in</h1>
                <p class="muted">To send a prescription, follow your orders and keep your details.</p>
            </div>

            @if (session('status'))
                <p class="form-note" role="status">{{ session('status') }}</p>
            @endif
            @error('email')
                @include('partials.form-alert', ['message' => $message])
            @enderror

            <form class="auth-form" method="post" action="{{ route('login.store') }}">
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" maxlength="255" required @error('email') aria-invalid="true" @enderror>
                </div>
                <div class="field">
                    <div class="field__row">
                        <label for="password">Password</label>
                        <a class="small-link" href="{{ route('password.request') }}">Forgot your password?</a>
                    </div>
                    @include('partials.password-input', ['id' => 'password', 'name' => 'password', 'autocomplete' => 'current-password', 'error' => $errors->has('password')])
                    @error('password')<p class="field__error">{{ $message }}</p>@enderror
                </div>

                @if ($captcha)
                    @include('partials.captcha')
                @endif

                <button class="btn btn--primary btn--block" type="submit">Log in</button>
                <p class="secure-note">
                    <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M9 1.5l6 2.5v4.5c0 3.8-2.6 6.6-6 8-3.4-1.4-6-4.2-6-8V4z"/><path d="M6.2 9l2 2 3.6-3.8" stroke-linecap="round"/></svg>
                    If you’ve turned on 2-step verification, we ask for the code from your app next.
                </p>
            </form>

            <div class="auth-card__foot">
                <p>New here?</p>
                <a class="btn btn--outline" href="{{ route('register') }}">Create an account</a>
            </div>
        </section>

        <aside class="auth-side" aria-labelledby="why-title">
            <h2 id="why-title">Your pharmacy account</h2>
            <ul class="feature-list">
                <li>
                    <span class="feature-list__icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M6 2.5h8l4 4v15H6z"/><path d="M14 2.5v4h4"/><path d="M9 12h6M9 16h4" stroke-linecap="round"/></svg></span>
                    <p><strong>Send a prescription</strong> from your phone, and collect it when we text you.</p>
                </li>
                <li>
                    <span class="feature-list__icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M3 7.5L12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5l9 4.5 9-4.5M12 12v9"/></svg></span>
                    <p><strong>Follow your orders</strong> and your weekly dose packs.</p>
                </li>
                <li>
                    <span class="feature-list__icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4.2 4.2-6.5 8-6.5s7 2.3 8 6.5"/></svg></span>
                    <p><strong>Keep your details</strong>, so checkout takes seconds.</p>
                </li>
            </ul>
            <div class="auth-side__note">
                <p class="auth-side__note-title">
                    <svg width="22" height="22" viewBox="0 0 18 18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M9 1.5l6 2.5v4.5c0 3.8-2.6 6.6-6 8-3.4-1.4-6-4.2-6-8V4z"/><path d="M6.2 9l2 2 3.6-3.8" stroke-linecap="round"/></svg>
                    Safer with 2-step verification
                </p>
                <p class="muted">Prescriptions are health data. With 2-step verification on, logging in also takes a code from your phone, so a stolen password alone gets nobody in.</p>
            </div>
        </aside>
    </div>
@endsection
