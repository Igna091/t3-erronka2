<?php

namespace App\Models;

use App\Support\OpeningHours;
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

    public function outboxEmails(): HasMany
    {
        return $this->hasMany(OutboxEmail::class);
    }

    /**
     * Queues the confirmation email for the Python mailer once the order is settled: paid by
     * card, or to be paid at the counter. Call it in the transaction that settles the order, so
     * the email exists exactly when the change does. Asking twice still queues one email.
     */
    public function queueConfirmationEmail(): void
    {
        $this->outboxEmails()->firstOrCreate(['kind' => 'order_confirmation'], ['payload' => $this->confirmationEmail()]);
    }

    /**
     * What the confirmation email shows, and nothing more: the health answers stay encrypted
     * in the order, and the mailer can't read the orders table.
     *
     * @return array<string, mixed>
     */
    public function confirmationEmail(): array
    {
        return [
            'to' => ['name' => $this->customer_name, 'email' => $this->email],
            'reference' => $this->reference,
            'placed_at' => $this->created_at->copy()->setTimezone(config('pharmacy.timezone'))->toIso8601String(),
            'phone' => $this->phone,
            'items' => $this->items()->orderBy('id')->get()->map(fn (OrderItem $item) => [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'line_cents' => $item->unit_price_cents * $item->quantity,
            ])->all(),
            'total_cents' => $this->total_cents,
            'payment' => ['status' => $this->payment_status, 'card_last4' => $this->card_last4],
            'ready_within' => config('shop.ready_within'),
            'pharmacy' => [
                'name' => config('app.name'),
                'address' => config('pharmacy.address'),
                'phone' => config('pharmacy.phone'),
                'hours' => OpeningHours::fromConfig()->summary(),
            ],
            'site_url' => config('app.url'),
        ];
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
