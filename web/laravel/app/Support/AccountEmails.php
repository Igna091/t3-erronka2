<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * The account's emails, queued in the email outbox for the Python mailer (python/, #22), like the
 * order confirmation. Each payload holds only what its template shows
 * (python/mailer/templates/<kind>/).
 */
final class AccountEmails
{
    public const VERIFY_MINUTES = 60;

    // Security alerts: what happened, in the words of the email's subject
    public const ALERTS = [
        'new_device_login',
        'password_changed',
        'password_reset',
        'two_factor_enabled',
        'two_factor_disabled',
        'recovery_code_used',
    ];

    public static function verifyEmail(User $user): void
    {
        self::queue($user, 'verify_email', [
            'url' => URL::temporarySignedRoute('verification.verify', now()->addMinutes(self::VERIFY_MINUTES), [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]),
            'expires_minutes' => self::VERIFY_MINUTES,
        ]);
    }

    public static function resetPassword(User $user, string $token): void
    {
        self::queue($user, 'reset_password', [
            'url' => route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]),
            'expires_minutes' => (int) config('auth.passwords.users.expire'),
        ]);
    }

    public static function securityAlert(User $user, string $event, ?Request $request = null): void
    {
        $request ??= request();
        self::queue($user, 'security_alert', [
            'event' => $event,
            'when' => now()->setTimezone(config('pharmacy.timezone'))->toIso8601String(),
            'device' => Device::describe($request->userAgent()),
            'ip' => (string) $request->ip(),
            'security_url' => route('account.security'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function queue(User $user, string $kind, array $payload): void
    {
        $user->outboxEmails()->create([
            'kind' => $kind,
            'payload' => [
                'to' => ['name' => $user->name, 'email' => $user->email],
                'first_name' => $user->firstName(),
                ...$payload,
                'pharmacy' => ['name' => config('app.name'), 'phone' => config('pharmacy.phone')],
                'site_url' => config('app.url'),
            ],
        ]);
    }
}
