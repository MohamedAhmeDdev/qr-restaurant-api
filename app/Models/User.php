<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'two_factor_enabled', 'two_factor_code', 'two_factor_expires_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean', 
            'two_factor_expires_at' => 'datetime', 
        ];
    }

    /**
     * System Roles assigned to the user (e.g. super_admin, restaurant_admin, cashier, waiter).
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    /**
     * Directly assigned restaurants (cashiers, waiters, managers, etc.).
     */
    public function assignedRestaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class, 'restaurant_user')->withTimestamps();
    }

    /**
     * Organizations owned by the user (Restaurant Admins / Org Owners).
     */
    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organizations::class, 'owner_id');
    }

    /**
     * Restaurants under owned organizations.
     */
    public function ownedRestaurants(): HasManyThrough
    {
        return $this->hasManyThrough(Restaurant::class, Organizations::class, 'owner_id', 'organization_id');
    }

    /**
     * Helper check to see if user possesses a specific role slug.
     */
    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }
}