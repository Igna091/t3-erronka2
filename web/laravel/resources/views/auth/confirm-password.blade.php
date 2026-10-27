@extends('layouts.account')

@section('title', 'Confirm it’s you · '.config('app.name'))

@section('header')
    @include('partials.header')
@endsection

@section('main')
    <div class="auth-single wrap">
        <section class="auth-card" aria-labelledby="confirm-title">
            <div class="auth-card__head">
                <h1 id="confirm-title">Confirm it’s you</h1>
                <p class="muted">Enter your password to change your security settings. We ask again after {{ intdiv((int) config('auth.password_timeout'), 60) }} minutes.</p>
            </div>
            <form class="auth-form" method="post" action="{{ route('password.confirm.store') }}">
                @csrf
                <div class="field">
                    <label for="password">Password</label>
                    @include('partials.password-input', ['id' => 'password', 'name' => 'password', 'autocomplete' => 'current-password', 'error' => $errors->has('password')])
                    @error('password')<p class="field__error">{{ $message }}</p>@enderror
                </div>
                <button class="btn btn--primary btn--block" type="submit">Continue</button>
            </form>
            <a class="small-link" href="{{ route('account.security') }}">Cancel</a>
        </section>
    </div>
@endsection
