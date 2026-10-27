<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * "Order received": only for the session or the account that placed the order; anyone else gets a 404.
     */
    public function show(Request $request, Order $order): View
    {
        abort_unless($order->isOwnedBy($request->session(), $request->user()), 404);

        return view('shop.order', ['order' => $order->load('items')]);
    }
}
