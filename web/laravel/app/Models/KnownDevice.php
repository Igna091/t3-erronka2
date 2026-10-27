<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A browser an account has logged in from, recognised by the random value in its device cookie
 * (stored here only as a SHA-256). A login from any other browser sends a security alert.
 */
class KnownDevice extends Model
{
    public const UPDATED_AT = null;

    public const COOKIE = '__Host-gfdevice';

    protected $fillable = ['token_hash', 'user_agent', 'last_used_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
