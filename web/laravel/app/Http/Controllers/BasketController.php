<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Support\Basket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BasketController extends Controller
{
    public function show(Basket $basket): View
    {
        $lines = $basket->lines();

        return view('shop.basket', [
            'lines' => $lines,
            'totalCents' => $lines->sum('line_cents'),
            'max' => $basket->max(),
        ]);
    }

    /**
     * Adds packs of a medicine, never more than the per-order limit. From a medicine's page the
     * visitor goes on to the basket; from the catalogue they stay on the card they used.
     */
    public function store(Request $request, Medicine $medicine, Basket $basket): RedirectResponse
    {
        abort_unless($medicine->active, 404);
        $packs = $request->validate(['quantity' => ['nullable', 'integer', 'between:1,'.$basket->max()]])['quantity'] ?? 1;

        $added = $basket->add($medicine, (int) $packs);
        $message = $added > 0
            ? "{$medicine->name} is in your basket."
            : "You already have {$basket->max()} packs of {$medicine->name}, the most per order.";

        if ($request->input('next') === 'basket') {
            return redirect()->route('basket.show')->with('status', $message);
        }

        return redirect()->to(url()->previous().'#'.$medicine->slug)->with('status', $message);
    }

    public function update(Request $request, Medicine $medicine, Basket $basket): RedirectResponse
    {
        $packs = $request->validate(['quantity' => ['required', 'integer', 'between:1,'.$basket->max()]])['quantity'];
        $basket->set($medicine, (int) $packs);

        return redirect()->route('basket.show');
    }

    public function destroy(Medicine $medicine, Basket $basket): RedirectResponse
    {
        $basket->remove($medicine);

        return redirect()->route('basket.show')->with('status', "{$medicine->name} is out of your basket.");
    }
}
