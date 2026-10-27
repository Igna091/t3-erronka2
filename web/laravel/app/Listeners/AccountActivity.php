<?php

namespace App\Listeners;

use App\Models\AccountEvent;
use App\Models\KnownDevice;
use App\Models\User;
use App\Support\AccountEmails;
use App\Support\Captcha;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * What happens to an account: written to its activity list (AccountEvent), and for the events
 * that matter, an alert email to the owner. Laravel finds these handlers itself (event discovery
 * of app/Listeners), one per event type.
 */
class AccountActivity
{
    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }
        $user = $event->user;
        Captcha::clearFailedLogins($user->email);
        AccountEvent::record($user, $user->hasTwoStep() ? 'login_two_step' : 'login');
        $this->checkDevice($user, request());
    }

    public function handleFailed(Failed $event): void
    {
        Captcha::recordFailedLogin(request(), $event->credentials['email'] ?? null);
        if ($event->user instanceof User) {
            AccountEvent::record($event->user, 'login_failed');
        }
    }

    public function handleTwoStepFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->record($event->user, 'two_step_failed');
    }

    public function handleTwoStepConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->recordAndAlert($event->user, 'two_factor_enabled');
    }

    public function handleTwoStepDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->recordAndAlert($event->user, 'two_factor_disabled');
    }

    public function handleRecoveryCodesGenerated(RecoveryCodesGenerated $event): void
    {
        $this->record($event->user, 'recovery_codes_regenerated');
    }

    public function handleRecoveryCodeUsed(RecoveryCodeReplaced $event): void
    {
        $this->recordAndAlert($event->user, 'recovery_code_used');
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->recordAndAlert($event->user, 'password_reset');
    }

    // Fortify's events carry the user untyped: anything but our User is ignored
    private function record(mixed $user, string $type): void
    {
        if ($user instanceof User) {
            AccountEvent::record($user, $type);
        }
    }

    private function recordAndAlert(mixed $user, string $type): void
    {
        if ($user instanceof User) {
            AccountEvent::record($user, $type);
            AccountEmails::securityAlert($user, $type);
        }
    }

    /**
     * Each browser gets a random value in a cookie at its first login; the account keeps only its
     * hash. A login from a browser without a known value sends an alert, except the very first,
     * at sign-up.
     */
    private function checkDevice(User $user, Request $request): void
    {
        $token = $request->cookie(KnownDevice::COOKIE);
        if (is_string($token) && $token !== '') {
            $known = $user->knownDevices()->where('token_hash', hash('sha256', $token))->first();
            if ($known !== null) {
                $known->forceFill(['last_used_at' => now()])->save();

                return;
            }
        }

        $first = ! $user->knownDevices()->exists();
        $token = Str::random(48);
        $user->knownDevices()->create([
            'token_hash' => hash('sha256', $token),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'last_used_at' => now(),
        ]);
        // __Host-: Secure, the whole site and this host only. 400 days, browsers' longest.
        Cookie::queue(Cookie::make(KnownDevice::COOKIE, $token, 60 * 24 * 400, '/', null, true, true, false, 'lax'));

        if (! $first) {
            AccountEmails::securityAlert($user, 'new_device_login', $request);
        }
    }
}
