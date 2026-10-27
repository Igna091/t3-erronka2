<?php

namespace App\Models;

use App\Support\AccountEmails;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * A customer's account. Its emails (confirm the address, reset the password, security alerts)
 * go into the email outbox for the Python mailer, never through Laravel's mail, so the model
 * has no Notifiable.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function sendEmailVerificationNotification(): void
    {
        AccountEmails::verifyEmail($this);
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        AccountEmails::resetPassword($this, $token);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<AccountEvent, $this>
     */
    public function accountEvents(): HasMany
    {
        return $this->hasMany(AccountEvent::class);
    }

    /**
     * @return HasMany<KnownDevice, $this>
     */
    public function knownDevices(): HasMany
    {
        return $this->hasMany(KnownDevice::class);
    }

    /**
     * @return HasMany<OutboxEmail, $this>
     */
    public function outboxEmails(): HasMany
    {
        return $this->hasMany(OutboxEmail::class);
    }

    public function firstName(): string
    {
        return Str::before(trim($this->name), ' ');
    }

    /**
     * "Ane Etxeberria" becomes "AE", for the avatar of an account without a photo.
     */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name)) ?: [];

        return Str::upper(implode('', array_map(fn (string $word) => mb_substr($word, 0, 1), array_slice($words, 0, 2))));
    }

    public function hasTwoStep(): bool
    {
        return $this->hasEnabledTwoFactorAuthentication();
    }
}
