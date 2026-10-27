<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\RequireCaptchaAfterFailedLogins;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\EmailVerifiedResponse;
use App\Http\Responses\PasswordResetLinkResponse;
use App\Support\Captcha;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Laravel\Fortify\Fortify;

/**
 * Accounts: login with 2-step verification, sign-up, email confirmation and password reset from
 * Laravel Fortify, with our pages (resources/views/auth) and our rules on top.
 */
class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The same answer whether or not the email has an account, so the "forgot your
        // password" form can't tell anyone who has one
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(VerifyEmailResponse::class, EmailVerifiedResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(function (Request $request) {
            $email = $request->old('email');

            return view('auth.login', [
                'captcha' => Captcha::requiredForLogin($request, is_string($email) ? $email : null),
            ]);
        });
        Fortify::twoFactorChallengeView(fn (Request $request) => view('auth.two-factor-challenge', [
            'recovery' => $request->boolean('recovery'),
        ]));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));

        // Fortify's login steps with the picture check in front. config('fortify.limiters.login')
        // is null, so EnsureLoginIsNotThrottled limits attempts (5 a minute per email and
        // address) and says so on the form, where a route limiter would answer with a 429 page.
        Fortify::authenticateThrough(fn () => [
            EnsureLoginIsNotThrottled::class,
            RequireCaptchaAfterFailedLogins::class,
            CanonicalizeUsername::class,
            RedirectIfTwoFactorAuthenticatable::class,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ]);

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
