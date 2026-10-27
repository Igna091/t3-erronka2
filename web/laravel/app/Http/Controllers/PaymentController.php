<?php

namespace App\Http\Controllers;

use App\Http\Requests\CardPaymentRequest;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The simulated card payment: the card form, the bank's one-time code, then approved or
 * declined. Nothing reaches a bank, and only the card's last 4 digits are kept.
 */
class PaymentController extends Controller
{
    public function show(Request $request, Order $order): View|RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        // Flashed once by store() or confirm(); without it, a finished payment goes to the order
        $result = $request->session()->get('payment_result');
        if ($result === null && ! $this->payable($order)) {
            return redirect()->route('orders.show', $order);
        }

        $check = $request->session()->get($this->checkKey($order));

        return view('shop.payment', [
            'order' => $order->load('items'),
            'step' => $result['step'] ?? ($check ? 'bank' : 'form'),
            'reason' => $result['reason'] ?? null,
            'last4' => $result['last4'] ?? $check['last4'] ?? null,
            'code' => $check['code'] ?? null,
        ]);
    }

    public function store(CardPaymentRequest $request, Order $order): RedirectResponse
    {
        if (! $this->payable($order)) {
            return redirect()->route('orders.show', $order);
        }

        $number = $request->validated('card_number');
        $last4 = substr($number, -4);

        if (config('shop.demo_cards')[$number] === 'declined') {
            $order->update(['payment_status' => 'declined']);

            return redirect()->route('payment.show', $order)
                ->with('payment_result', ['step' => 'declined', 'reason' => 'card', 'last4' => $last4]);
        }

        // The bank's check: a one-time code "sent by text message", shown on the simulated phone
        $request->session()->put($this->checkKey($order), [
            'code' => (string) random_int(100000, 999999),
            'last4' => $last4,
            'attempts' => 0,
        ]);

        return redirect()->route('payment.show', $order);
    }

    /**
     * Checks the one-time code. Three wrong codes and the bank declines the payment.
     */
    public function confirm(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);
        $key = $this->checkKey($order);
        $check = $request->session()->get($key);

        if ($check === null || ! $this->payable($order)) {
            return redirect()->route('payment.show', $order);
        }

        $code = (string) preg_replace('/\s+/', '', (string) $request->input('code'));

        if (! hash_equals($check['code'], $code)) {
            if (++$check['attempts'] >= 3) {
                $request->session()->forget($key);
                $order->update(['payment_status' => 'declined']);

                return redirect()->route('payment.show', $order)
                    ->with('payment_result', ['step' => 'declined', 'reason' => 'code', 'last4' => $check['last4']]);
            }

            $request->session()->put($key, $check);

            return redirect()->route('payment.show', $order)
                ->withErrors(['code' => 'That code doesn’t match the one in the text message.']);
        }

        $request->session()->forget($key);
        DB::transaction(function () use ($order, $check) {
            $order->update(['payment_status' => 'paid', 'card_last4' => $check['last4']]);
            $order->queueConfirmationEmail();
        });

        return redirect()->route('payment.show', $order)
            ->with('payment_result', ['step' => 'approved', 'last4' => $check['last4']]);
    }

    /**
     * Switches an unpaid card order to paying at the counter.
     */
    public function counter(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        if ($this->payable($order)) {
            $request->session()->forget($this->checkKey($order));
            DB::transaction(function () use ($order) {
                $order->update(['payment_method' => 'counter', 'payment_status' => 'at_counter']);
                $order->queueConfirmationEmail();
            });
        }

        return redirect()->route('orders.show', $order);
    }

    private function payable(Order $order): bool
    {
        return $order->payment_method === 'card' && in_array($order->payment_status, ['pending', 'declined'], true);
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($order->isOwnedBy($request->session(), $request->user()), 404);
    }

    private function checkKey(Order $order): string
    {
        return 'payment_check.'.$order->id;
    }
}
