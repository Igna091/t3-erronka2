@extends('layouts.account')

@section('title', 'Turn off 2-step verification · '.config('app.name'))

@section('header')
    @include('partials.header')
@endsection

@section('main')
    <div class="auth-single wrap">
        <section class="auth-card" aria-labelledby="off-title">
            <div class="auth-card__head">
                <h1 id="off-title">Turn off 2-step verification</h1>
                <p class="muted">Logging in will take only your password again. To check it’s you, type the code your app shows now, or one of your recovery codes.</p>
            </div>
            <form class="auth-form" method="post" action="{{ route('two-factor.disable') }}">
                @csrf
                @method('delete')
                <div class="field">
                    <label for="code">Code from your app, or a recovery code</label>
                    <input class="input--code" id="code" name="code" type="text" autocomplete="one-time-code" autocapitalize="off" spellcheck="false" maxlength="40" required @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                    @error('code')<p class="field__error" id="code-error">{{ $message }}</p>@enderror
                </div>
                <div class="actions">
                    <button class="btn btn--danger" type="submit">Turn it off</button>
                    <a class="small-link" href="{{ route('account.security') }}">Keep it on</a>
                </div>
            </form>
        </section>
    </div>
@endsection
