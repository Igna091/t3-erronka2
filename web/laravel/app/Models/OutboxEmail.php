<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An email waiting for the Python mailer (python/, #22), which sends it and records the
 * outcome. The site only ever adds these.
 *
 * @property array<string, mixed>|null $payload
 */
class OutboxEmail extends Model
{
    protected $table = 'email_outbox';

    protected $fillable = ['kind', 'payload'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'next_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
