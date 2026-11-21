<?php

namespace App\Services;

use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CommerceService
{
    public function checkout(User $user, array $cart): Order
    {
        $this->expire();

        return DB::transaction(function () use ($user, $cart): Order {
            if (! $cart || count($cart) > 30) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty or too large.']);
            }
            $products = Product::with('asset')->whereIn('id', array_keys($cart))->get()->keyBy('id');
            $order = Order::create(['number' => (string) Str::uuid(), 'user_id' => $user->id, 'total' => 0, 'currency' => config('commerce.currency'), 'status' => 'pending', 'expires_at' => now()->addMinutes(config('commerce.reservation_minutes'))]);
            $total = 0;
            $painting = false;
            foreach ($cart as $id => $quantity) {
                $product = $products->get($id);
                $quantity = (int) $quantity;
                if (! $product || $product->status !== 'published' || ! $product->ready || $quantity < 1 || $quantity > 10) {
                    throw ValidationException::withMessages(['cart' => 'An item is unavailable. Review your cart.']);
                }
                if ($product->type !== 'painting' && ($quantity !== 1 || $user->owns($product) || $user->orders()->where('status', 'pending')->whereHas('items', fn ($query) => $query->where('product_id', $product->id))->exists())) {
                    throw ValidationException::withMessages(['cart' => "You already own {$product->title} or have a pending checkout for it."]);
                }
                if ($product->type === 'painting') {
                    $painting = true;
                    if (! Product::whereKey($product->id)->where('stock', '>=', $quantity)->decrement('stock', $quantity)) {
                        throw ValidationException::withMessages(['cart' => "{$product->title} is no longer in stock."]);
                    }
                    StockReservation::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => $quantity]);
                }
                $order->items()->create(['product_id' => $product->id, 'title' => $product->title, 'type' => $product->type, 'quantity' => $quantity, 'unit_price' => $product->price]);
                $total += $product->price * $quantity;
            }
            $order->update(['total' => $total, 'fulfillment_status' => $painting ? 'awaiting' : null, 'pickup_instructions' => $painting ? Setting::read('pickup_instructions', 'We will contact you when your painting is ready for collection.') : null]);

            return $order;
        }, 5);
    }

    public function settle(Payment $payment, string $outcome): void
    {
        DB::transaction(function () use ($payment, $outcome): void {
            $order = Order::lockForUpdate()->findOrFail($payment->order_id);
            $payment->refresh();
            if ($order->status !== 'pending' || $payment->status !== 'pending') {
                return;
            }
            if ($order->expires_at->isPast()) {
                $this->release($order, 'expired');

                return;
            }
            if ($outcome !== 'success') {
                $this->release($order, $outcome === 'cancel' ? 'cancelled' : 'failed');

                return;
            }
            if ($payment->amount !== $order->total || $payment->currency !== $order->currency) {
                throw ValidationException::withMessages(['payment' => 'Payment total does not match this order.']);
            }
            $order->update(['status' => 'paid', 'paid_at' => now()]);
            $payment->update(['status' => 'paid']);
            $order->reservations()->where('status', 'held')->update(['status' => 'consumed']);
            foreach ($order->items as $item) {
                if ($item->type !== 'painting') {
                    Entitlement::updateOrCreate(['user_id' => $order->user_id, 'product_id' => $item->product_id], ['order_id' => $order->id, 'progress' => 1, 'revoked_at' => null]);
                }
            }
            Audit::record('payment.simulated.paid', $order->number);
        }, 5);
    }

    public function refund(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $order = Order::lockForUpdate()->findOrFail($payment->order_id);
            if ($order->status === 'refunded') {
                return;
            }
            if ($order->status !== 'paid' || $order->fulfillment_status === 'collected') {
                throw ValidationException::withMessages(['refund' => 'Only paid, uncollected orders can be refunded here.']);
            }
            $order->update(['status' => 'refunded']);
            $payment->update(['status' => 'refunded']);
            Entitlement::where('order_id', $order->id)->update(['revoked_at' => now()]);
            foreach ($order->reservations()->where('status', 'consumed')->get() as $reservation) {
                Product::withTrashed()->whereKey($reservation->product_id)->increment('stock', $reservation->quantity);
                $reservation->update(['status' => 'returned']);
            }
            Audit::record('payment.simulated.refunded', $order->number);
        }, 5);
    }

    public function expire(): void
    {
        Order::where('status', 'pending')->where('expires_at', '<=', now())->pluck('id')->each(function (int $id): void {
            DB::transaction(function () use ($id): void {
                $order = Order::lockForUpdate()->find($id);
                if ($order && $order->status === 'pending' && $order->expires_at->isPast()) {
                    $this->release($order, 'expired');
                }
            }, 5);
        });
    }

    private function release(Order $order, string $status): void
    {
        foreach ($order->reservations()->where('status', 'held')->get() as $reservation) {
            Product::withTrashed()->whereKey($reservation->product_id)->increment('stock', $reservation->quantity);
            $reservation->update(['status' => 'released']);
        }
        $order->update(['status' => $status]);
        $order->payment()->update(['status' => $status]);
        Audit::record('payment.simulated.'.$status, $order->number);
    }
}
