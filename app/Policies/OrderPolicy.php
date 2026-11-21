<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->is_active && ($user->is_admin || $order->user_id === $user->id);
    }

    public function pay(User $user, Order $order): bool
    {
        return $user->is_active && $order->user_id === $user->id;
    }
}
