@extends('layouts.account')

@section('title', 'Security · '.config('app.name'))

@section('header')
    @include('partials.header')
@endsection

@php($madrid = config('pharmacy.timezone'))

@section('main')
    <div class="account-layout wrap">
        @include('partials.account-nav')

        <div class="account-content">
            @include('partials.account-status')
            <div class="account-title">
                <h1>Security</h1>
                <p class="muted">Your account will hold prescriptions, so keep it locked.</p>
            </div>

            <section class="panel-card panel-card--row" aria-labelledby="password-title">
                <div>
                    <h2 id="password-title">Password</h2>
                    <p class="muted">
                        @if ($user->password_changed_at)
                            Last changed on {{ $user->password_changed_at->setTimezone($madrid)->format('j F Y') }}.
                        @else
                            Not changed since the account was opened.
                        @endif
                    </p>
                </div>
                <a class="btn btn--outline btn--small" href="{{ route('account.password') }}">Change password</a>
            </section>

            <section class="panel-card" aria-labelledby="twostep-title">
                <div class="panel-card__head panel-card__head--start">
                    <h2 id="twostep-title">2-step verification</h2>
                    @if ($user->hasTwoStep())
                        <span class="status-pill status-pill--ok">On</span>
                    @else
                        <span class="status-pill">Off</span>
                    @endif
                </div>

                @if ($user->hasTwoStep())
                    <p class="muted">Logging in takes your password and a code from your authenticator app.</p>
                    <div class="rows">
                        <div class="rows__item">
                            <div>
                                <h3>Authenticator app</h3>
                                <p class="muted">Added on {{ $user->two_factor_confirmed_at?->setTimezone($madrid)->format('j F Y') }}. New phone? Turn 2-step verification off and on again with it.</p>
                            </div>
                        </div>
                        <div class="rows__item">
                            <div>
                                <h3>Recovery codes</h3>
                                <p class="muted">{{ $codesLeft }} of 8 left. Each one logs you in once if you lose your phone.</p>
                            </div>
                            <div class="actions">
                                <a class="btn btn--outline btn--small" href="{{ route('account.recovery-codes') }}">Show codes</a>
                            </div>
                        </div>
                    </div>
                    <div class="actions actions--note">
                        <a class="btn btn--danger btn--small" href="{{ route('account.two-step.turn-off') }}">Turn off 2-step verification</a>
                        <p class="muted">We ask for your password and a code first, and email you when it’s off.</p>
                    </div>
                @else
                    <div class="nudge nudge--inline">
                        <div>
                            <h3>Recommended</h3>
                            <p>With it on, logging in also takes a code from your phone, so a stolen password alone gets nobody into your prescriptions.</p>
                        </div>
                        <a class="btn btn--primary btn--small" href="{{ route('account.two-step') }}">Turn it on</a>
                    </div>
                @endif
            </section>

            <section class="panel-card" aria-labelledby="devices-title">
                <h2 id="devices-title">Where you’re logged in</h2>
                <ul class="rows">
                    @foreach ($sessions as $session)
                        <li class="rows__item">
                            <div>
                                <p class="rows__title">{{ $session['device'] }} @if ($session['current'])<span class="tag">This device</span>@endif</p>
                                <p class="muted">{{ $session['ip'] }} · {{ $session['current'] ? 'Active now' : 'Active '.$session['lastActive']->diffForHumans() }}</p>
                            </div>
                            @unless ($session['current'])
                                <form method="post" action="{{ route('account.sessions.end', $session['key']) }}">
                                    @csrf
                                    @method('delete')
                                    <button class="btn btn--outline btn--small" type="submit">Log out</button>
                                </form>
                            @endunless
                        </li>
                    @endforeach
                </ul>
                <div class="actions actions--note">
                    <form method="post" action="{{ route('account.sessions.end-others') }}">
                        @csrf
                        @method('delete')
                        <button class="btn btn--dark btn--small" type="submit">Log out everywhere else</button>
                    </form>
                    <p class="muted">Each address is the internet connection the browser used last.</p>
                </div>
            </section>

            <section class="panel-card" aria-labelledby="activity-title">
                <h2 id="activity-title">Recent activity</h2>
                @if ($events->isEmpty())
                    <p class="muted">Nothing yet.</p>
                @else
                    <div class="table-scroll">
                        <table class="activity">
                            <thead>
                                <tr><th scope="col">When</th><th scope="col">What</th><th scope="col">Device</th><th scope="col">Address</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($events as $event)
                                    <tr>
                                        <td class="nowrap">{{ $event->created_at?->setTimezone($madrid)->format('j M Y, G:i') }}</td>
                                        <td @class(['activity__what', 'activity__what--warning' => $event->isWarning()])>{{ $event->label() }}</td>
                                        <td>{{ $event->device() }}</td>
                                        <td>{{ $event->ip_address }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <p class="muted">Something you don’t recognise? Change your password, log out everywhere else, and call us on <a href="tel:{{ config('pharmacy.phone.dial') }}">{{ config('pharmacy.phone.display') }}</a>.</p>
            </section>

            <section class="panel-card panel-card--band" aria-labelledby="alerts-title">
                <h2 id="alerts-title">We email you about changes</h2>
                <p class="muted">When someone logs in from a new browser, when your password changes, when 2-step verification is turned on or off, and when a recovery code is used. These emails can’t be turned off, so you always hear about them.</p>
            </section>
        </div>
    </div>
@endsection
