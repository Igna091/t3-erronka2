@extends('layouts.account')

@section('title', 'Create your account · '.config('app.name'))

@section('header')
    @include('partials.auth-header', ['asidePrefix' => 'Already have an account?', 'asideLink' => route('login'), 'asideText' => 'Log in'])
@endsection

@section('main')
    <div class="auth-grid wrap">
        <section class="auth-card auth-card--wide" aria-labelledby="signup-title">
            <div class="auth-card__head">
                <h1 id="signup-title">Create your account</h1>
                <p class="muted">It takes a minute. Next, we email you a link to confirm the address.</p>
            </div>

            @if ($errors->any())
                @include('partials.form-alert', ['title' => 'Before we create the account:', 'message' => 'check the fields marked below.'])
            @endif

            <form class="auth-form" method="post" action="{{ route('register.store') }}">
                @csrf
                <div class="field">
                    <label for="name">Full name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="100" required @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                    @error('name')<p class="field__error" id="name-error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required aria-describedby="email-help @error('email') email-error @enderror" @error('email') aria-invalid="true" @enderror>
                    <p class="field__help" id="email-help">Order updates and security alerts go here.</p>
                    @error('email')<p class="field__error" id="email-error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    @include('partials.password-input', ['id' => 'password', 'name' => 'password', 'autocomplete' => 'new-password', 'describedby' => 'password-rules', 'error' => $errors->has('password')])
                    @error('password')<p class="field__error">{{ $message }}</p>@enderror
                    @include('partials.password-rules')
                </div>

                @include('partials.captcha', ['why' => 'It stops programs from opening accounts in bulk.'])

                <div class="field">
                    <div class="consent">
                        <input id="privacy" name="privacy" type="checkbox" value="1" @checked(old('privacy')) required @error('privacy') aria-invalid="true" aria-describedby="privacy-error" @enderror>
                        <label for="privacy">I’ve read the <a href="#">privacy notice</a>: how the pharmacy uses and protects my details.</label>
                    </div>
                    @error('privacy')<p class="field__error" id="privacy-error">{{ $message }}</p>@enderror
                </div>

                <button class="btn btn--primary btn--block" type="submit">Create account</button>
                <p class="field__help">We never sell or share your details. You can delete the account at any time.</p>
            </form>
        </section>

        <aside class="auth-side" aria-labelledby="next-title">
            <h2 id="next-title">What happens next</h2>
            <ol class="next-steps">
                <li>
                    <span class="next-steps__mark is-current" aria-hidden="true">1</span>
                    <div>
                        <h3>Confirm your email</h3>
                        <p class="muted">We send a link that works for 60 minutes.</p>
                    </div>
                </li>
                <li>
                    <span class="next-steps__mark" aria-hidden="true">2</span>
                    <div>
                        <h3>Protect your account</h3>
                        <p class="muted">Turn on 2-step verification. We recommend it, because prescriptions are health data.</p>
                    </div>
                </li>
                <li>
                    <span class="next-steps__mark" aria-hidden="true">3</span>
                    <div>
                        <h3>Send your first prescription</h3>
                        <p class="muted">Take a photo of it, and we text you when it’s ready to collect.</p>
                    </div>
                </li>
            </ol>
        </aside>
    </div>
@endsection
