<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

final class ProductPolicy
{
    public function manage(User $user): bool
    {
        return $user->is_admin && $user->is_active;
    }

    public function consume(User $user, Product $product): bool
    {
        return $product->type !== 'painting' && $user->owns($product);
    }
}
