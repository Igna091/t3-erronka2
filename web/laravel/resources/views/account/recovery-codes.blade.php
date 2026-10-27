@extends('layouts.account')

@section('title', 'Recovery codes · '.config('app.name'))

@section('header')
    @include('partials.header')
@endsection

@section('main')
    <div class="account-layout wrap">
        @include('partials.account-nav')

        <div class="account-content">
            @include('partials.account-status')
            <div class="account-title">
                <h1>Recovery codes</h1>
                <p class="muted">Each one logs you in once if you lose your phone. Used codes are gone from the list.</p>
            </div>
            <section class="panel-card" aria-label="Your recovery codes">
                @include('account.partials.recovery-codes', ['codes' => $codes])
            </section>
            <section class="panel-card panel-card--row" aria-labelledby="new-codes-title">
                <div>
                    <h2 id="new-codes-title">Get new codes</h2>
                    <p class="muted">Running out, or think someone saw them? New codes replace all of these.</p>
                </div>
                <form method="post" action="{{ route('account.recovery-codes.regenerate') }}">
                    @csrf
                    <button class="btn btn--outline btn--small" type="submit">Get new codes</button>
                </form>
            </section>
            <a class="small-link" href="{{ route('account.security') }}">Back to Security</a>
        </div>
    </div>
@endsection
