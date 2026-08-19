<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->hasPermission('orders.view')
            && $user->restaurant_id === $order->restaurant_id;
    }

    public function updateStatus(
        User $user,
        Order $order
    ): bool {
        return $user->hasPermission('orders.update_status')
            && $user->restaurant_id === $order->restaurant_id;
    }

    public function cancel(
        User $user,
        Order $order
    ): bool {
        return $user->hasPermission('orders.cancel')
            && $user->restaurant_id === $order->restaurant_id;
    }
}