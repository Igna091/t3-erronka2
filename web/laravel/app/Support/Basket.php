<?php

namespace App\Support;

use App\Models\Medicine;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * The visitor's basket, kept in their session as medicine id => packs. No medicine can be in
 * it more than config('shop.max_packs') times: the most the pharmacy sells in one order.
 */
class Basket
{
    public function __construct(private readonly Session $session) {}

    public function max(): int
    {
        return (int) config('shop.max_packs');
    }

    public function quantity(Medicine $medicine): int
    {
        return $this->quantities()[$medicine->id] ?? 0;
    }

    /**
     * Adds up to $packs packs; returns how many fit under the limit.
     */
    public function add(Medicine $medicine, int $packs = 1): int
    {
        $current = $this->quantity($medicine);
        $added = max(0, min($packs, $this->max() - $current));

        if ($added > 0) {
            $this->put($medicine->id, $current + $added);
        }

        return $added;
    }

    public function set(Medicine $medicine, int $packs): void
    {
        if ($this->quantity($medicine) > 0) {
            $this->put($medicine->id, max(1, min($this->max(), $packs)));
        }
    }

    public function remove(Medicine $medicine): void
    {
        $quantities = $this->quantities();
        unset($quantities[$medicine->id]);
        $this->session->put('basket', $quantities);
    }

    public function clear(): void
    {
        $this->session->forget('basket');
    }

    public function count(): int
    {
        return array_sum($this->quantities());
    }

    /**
     * What is in the basket, in the order it was added. Medicines taken off sale since are left out.
     *
     * @return Collection<int, array{medicine: Medicine, quantity: int, line_cents: int}>
     */
    public function lines(): Collection
    {
        $quantities = $this->quantities();
        $medicines = Medicine::active()->whereIn('id', array_keys($quantities))->get()->keyBy('id');

        return collect($quantities)
            ->filter(fn (int $packs, int $id) => $medicines->has($id))
            ->map(fn (int $packs, int $id) => [
                'medicine' => $medicines[$id],
                'quantity' => $packs,
                'line_cents' => $medicines[$id]->price_cents * $packs,
            ])
            ->values();
    }

    /**
     * @return array<int, int>
     */
    private function quantities(): array
    {
        return $this->session->get('basket', []);
    }

    private function put(int $id, int $packs): void
    {
        $quantities = $this->quantities();
        $quantities[$id] = $packs;
        $this->session->put('basket', $quantities);
    }
}
