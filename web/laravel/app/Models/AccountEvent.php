<?php

namespace App\Models;

use App\Support\Device;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Something that happened to an account, for its "Recent activity" list on the Security page.
 */
class AccountEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * What the Security page calls each type.
     */
    public const LABELS = [
        'login' => 'Logged in',
        'login_two_step' => 'Logged in, with a code from the app',
        'login_failed' => 'Wrong password',
        'two_step_failed' => 'Wrong 2-step code',
        'recovery_code_used' => 'Logged in with a recovery code',
        'password_changed' => 'Password changed',
        'password_reset' => 'Password reset with an email link',
        'two_factor_enabled' => '2-step verification turned on',
        'two_factor_disabled' => '2-step verification turned off',
        'recovery_codes_regenerated' => 'New recovery codes made',
        'sessions_ended' => 'Logged out of other devices',
    ];

    protected $fillable = ['type', 'ip_address', 'user_agent'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public static function record(User $user, string $type, ?Request $request = null): void
    {
        $request ??= request();
        $user->accountEvents()->create([
            'type' => $type,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? $this->type;
    }

    public function isWarning(): bool
    {
        return in_array($this->type, ['login_failed', 'two_step_failed', 'two_factor_disabled', 'recovery_code_used'], true);
    }

    public function device(): string
    {
        return Device::describe($this->user_agent);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
