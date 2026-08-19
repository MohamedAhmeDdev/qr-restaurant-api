<?php

namespace App\Policies;

use App\Models\RestaurantTable;
use App\Models\User;

class RestaurantTablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tables.view');
    }

    public function view(
        User $user,
        RestaurantTable $table
    ): bool {
        return $this->belongsToTenant($user, $table)
            && $user->hasPermission('tables.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tables.create');
    }

    public function update(
        User $user,
        RestaurantTable $table
    ): bool {
        return $this->belongsToTenant($user, $table)
            && $user->hasPermission('tables.update');
    }

    public function delete(
        User $user,
        RestaurantTable $table
    ): bool {
        return $this->belongsToTenant($user, $table)
            && $user->hasPermission('tables.delete');
    }

    private function belongsToTenant(
        User $user,
        RestaurantTable $table
    ): bool {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->restaurant_id === $table->restaurant_id;
    }
}