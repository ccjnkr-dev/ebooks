<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Product;
use App\Services\CommerceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class CheckoutController extends Controller
{
    public function cart(Request $request): View
    {
        $cart = $request->session()->get('cart', []);

        return view('cart', ['products' => Product::with('asset')->whereIn('id', array_keys($cart))->get(), 'cart' => $cart]);
    }

    public function add(Request $request, Product $product): RedirectResponse
    {
        $product->load('asset');
        abort_unless($product->status === 'published' && $product->ready, 422, 'Content is not ready for purchase.');
        $data = $request->validate(['quantity' => 'nullable|integer|min:1|max:10']);
        $cart = $request->session()->get('cart', []);
        $cart[$product->id] = $product->type === 'painting' ? ($data['quantity'] ?? 1) : 1;
        $request->session()->put('cart', $cart);

        return redirect()->route('cart')->with('success', 'Added to your collection bag.');
    }

    public function remove(Request $request, string $product): RedirectResponse
    {
        $cart = $request->session()->get('cart', []);
        unset($cart[$product]);
        $request->session()->put('cart', $cart);

        return back()->with('success', 'Item removed.');
    }

    public function checkout(Request $request, CommerceService $commerce, PaymentGateway $gateway): RedirectResponse
    {
        $request->validate(['accept_pickup' => 'accepted']);
        $order = DB::transaction(function () use ($request, $commerce, $gateway): Order {
            $order = $commerce->checkout($request->user(), $request->session()->get('cart', []));
            $gateway->begin($order);

            return $order;
        }, 5);
        $request->session()->forget('cart');

        return redirect()->route('payment', $order);
    }

    public function payment(Order $order, CommerceService $commerce): View
    {
        Gate::authorize('pay', $order);
        $commerce->expire();
        $order->refresh()->load(['items', 'payment']);
        abort_unless($order->payment, 404);

        return view('payment', ['order' => $order]);
    }

    public function confirm(Request $request, Order $order, PaymentGateway $gateway): RedirectResponse
    {
        Gate::authorize('pay', $order);
        $data = $request->validate(['outcome' => 'required|in:success,failure,cancel']);
        $gateway->confirm($order->payment()->firstOrFail(), $data['outcome']);

        return redirect()->route('orders.show', $order)->with('success', 'Simulation complete. Check the order status below.');
    }
}
