@extends('layouts.account')

@section('title', 'Change password · '.config('app.name'))

@section('header')
    @include('partials.header')
@endsection

@section('main')
    <div class="account-layout wrap">
        @include('partials.account-nav')

        <div class="account-content account-content--narrow">
            @include('partials.account-status')
            <div class="account-title">
                <h1>Change password</h1>
                <p class="muted">Every other browser is logged out when it changes, and we email you about it.</p>
            </div>

            <form class="panel-card auth-form" method="post" action="{{ route('user-password.update') }}">
                @csrf
                @method('put')
                <div class="field">
                    <label for="current_password">Current password</label>
                    @include('partials.password-input', ['id' => 'current_password', 'name' => 'current_password', 'autocomplete' => 'current-password', 'error' => $errors->updatePassword->has('current_password')])
                    @if ($errors->updatePassword->has('current_password'))<p class="field__error">{{ $errors->updatePassword->first('current_password') }}</p>@endif
                </div>
                <div class="field">
                    <label for="password">New password</label>
                    @include('partials.password-input', ['id' => 'password', 'name' => 'password', 'autocomplete' => 'new-password', 'describedby' => 'password-rules', 'error' => $errors->updatePassword->has('password')])
                    @if ($errors->updatePassword->has('password'))<p class="field__error">{{ $errors->updatePassword->first('password') }}</p>@endif
                    @include('partials.password-rules')
                </div>
                <div class="actions">
                    <button class="btn btn--primary" type="submit">Save the new password</button>
                    <a class="small-link" href="{{ route('account.security') }}">Back to Security</a>
                </div>
            </form>
        </div>
    </div>
@endsection
