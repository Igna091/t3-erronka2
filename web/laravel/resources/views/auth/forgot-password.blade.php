@extends('layouts.account')

@section('title', 'Forgot your password · '.config('app.name'))

@section('header')
    @include('partials.auth-header', ['asideLink' => route('login'), 'asideText' => 'Back to log in'])
@endsection

@section('main')
    <div class="auth-single wrap">
        <section class="auth-card" aria-labelledby="forgot-title">
            <div class="auth-card__head">
                <h1 id="forgot-title">Forgot your password?</h1>
                <p class="muted">Type your email and we send you a link to choose a new one.</p>
            </div>

            @if (session('status'))
                <p class="form-note form-note--done" role="status">{{ session('status') }}</p>
            @endif

            <form class="auth-form" method="post" action="{{ route('password.email') }}">
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" maxlength="255" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                    @error('email')<p class="field__error" id="email-error">{{ $message }}</p>@enderror
                </div>
                <button class="btn btn--primary btn--block" type="submit">Send me a link</button>
            </form>
            <p class="field__help">Changing the password logs you out on every other device, and we email you that it changed.</p>
        </section>
    </div>
@endsection
