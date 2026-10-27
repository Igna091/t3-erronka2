@extends('layouts.account')

@section('title', 'Choose a new password · '.config('app.name'))

@section('header')
    @include('partials.auth-header', ['asideLink' => route('login'), 'asideText' => 'Back to log in'])
@endsection

@section('main')
    <div class="auth-single wrap">
        <section class="auth-card" aria-labelledby="reset-title">
            <div class="auth-card__head">
                <h1 id="reset-title">Choose a new password</h1>
                <p class="muted">For {{ old('email', $request->query('email')) }}.</p>
            </div>

            @error('email')
                @include('partials.form-alert', ['message' => $message])
            @enderror

            <form class="auth-form" method="post" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">
                <input type="hidden" name="email" value="{{ old('email', $request->query('email')) }}">
                <div class="field">
                    <label for="password">New password</label>
                    @include('partials.password-input', ['id' => 'password', 'name' => 'password', 'autocomplete' => 'new-password', 'describedby' => 'password-rules', 'error' => $errors->has('password')])
                    @error('password')<p class="field__error">{{ $message }}</p>@enderror
                    @include('partials.password-rules')
                </div>
                <button class="btn btn--primary btn--block" type="submit">Save the new password</button>
            </form>
        </section>
    </div>
@endsection
