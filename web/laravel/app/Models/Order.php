<?php

namespace App\Models;

use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An order placed in the shop. There are no customer accounts: only the session that placed
 * an order may see it or pay for it.
 */
class Order extends Model
{
    protected $fillable = [
        'reference', 'customer_name', 'email', 'phone',
        'taker', 'age', 'pregnant', 'health_notes', 'allergies', 'health_consent_at',
        'payment_method', 'payment_status', 'card_last4', 'total_cents', 'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // The pharmacist's questions are health data (GDPR art. 9): encrypted at rest with APP_KEY
            'age' => 'encrypted',
            'pregnant' => 'encrypted',
            'health_notes' => 'encrypted',
            'allergies' => 'encrypted',
            'health_consent_at' => 'datetime',
            'total_cents' => 'integer',
        ];
    }

    /**
     * A hard-to-guess reference for URLs and the customer, e.g. "GF-7KQ2M9XA".
     */
    public static function newReference(): string
    {
        return 'GF-'.Str::upper(Str::random(8));
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function grantTo(Session $session): void
    {
        $session->push('orders', $this->id);
    }

    public function isOwnedBy(Session $session): bool
    {
        return in_array($this->id, $session->get('orders', []), true);
    }
}
