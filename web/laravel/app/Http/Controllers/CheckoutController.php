<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Support\Basket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function show(Basket $basket): View|RedirectResponse
    {
        $lines = $basket->lines();
        if ($lines->isEmpty()) {
            return redirect()->route('basket.show');
        }

        return view('shop.checkout', [
            'lines' => $lines,
            'totalCents' => $lines->sum('line_cents'),
        ]);
    }

    /**
     * Places the order with today's prices, empties the basket and goes on to the card payment
     * or, when paying at the counter, straight to the order.
     */
    public function store(CheckoutRequest $request, Basket $basket): RedirectResponse
    {
        $lines = $basket->lines();
        if ($lines->isEmpty()) {
            return redirect()->route('basket.show');
        }

        $answers = $request->validated();
        $order = DB::transaction(function () use ($answers, $lines, $request) {
            $order = Order::create([
                // Logged in: the order is also the account's, on any device
                'user_id' => $request->user()?->getAuthIdentifier(),
                'reference' => Order::newReference(),
                'customer_name' => $answers['name'],
                'email' => $answers['email'],
                'phone' => $answers['phone'],
                'taker' => $answers['taker'],
                'age' => (string) $answers['age'],
                'pregnant' => $answers['pregnant'],
                'health_notes' => $answers['health_notes'] ?? null,
                'allergies' => $answers['allergies'] ?? null,
                'health_consent_at' => now(),
                'payment_method' => $answers['payment'],
                'payment_status' => $answers['payment'] === 'card' ? 'pending' : 'at_counter',
                'total_cents' => $lines->sum('line_cents'),
                'status' => 'received',
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'medicine_id' => $line['medicine']->id,
                    'name' => $line['medicine']->name,
                    'unit_price_cents' => $line['medicine']->price_cents,
                    'quantity' => $line['quantity'],
                ]);
            }

            // Paying at the counter settles the order straight away
            if ($order->payment_status === 'at_counter') {
                $order->queueConfirmationEmail();
            }

            return $order;
        });

        $basket->clear();
        $order->grantTo($request->session());

        return $order->payment_method === 'card'
            ? redirect()->route('payment.show', $order)
            : redirect()->route('orders.show', $order);
    }
}
