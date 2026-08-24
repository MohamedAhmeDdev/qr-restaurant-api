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

#[Fillable(['name', 'email', 'password', 'two_factor_enabled', 'is_super_admin', 'two_factor_code', 'two_factor_expires_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
              'two_factor_enabled' => 'boolean', 
           'two_factor_expires_at' => 'datetime', 
        ];
    }

    /**
     * Organizations owned by the user (if they are an Organizations Owner).
     */
    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organizations::class, 'owner_id');
    }

    /**
     * Restaurants accessible to the user via owned Organizations.
     */
    public function ownedRestaurants(): HasManyThrough
    {
        return $this->hasManyThrough(Restaurant::class, Organizations::class, 'owner_id', 'organization_id');
    }

    /**
     * Assigned restaurant venues via explicit user_roles entries.
     */
    public function assignedRestaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class, 'user_roles')
                    ->withPivot('role_id')
                    ->withTimestamps();
    }

    /**
     * Roles assigned to the user scoped to specific restaurants.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
                    ->withPivot('restaurant_id')
                    ->withTimestamps();
    }
}