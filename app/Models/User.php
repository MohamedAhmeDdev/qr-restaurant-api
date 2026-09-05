<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'two_factor_enabled',
        'two_factor_code',
        'two_factor_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'two_factor_expires_at' => 'datetime',
        ];
    }

   public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class, 'user_roles')
                ->withPivot('status')
                ->withTimestamps();
}

    /**
     * Directly assigned restaurants (cashiers, waiters, managers, etc.).
     */
   public function assignedRestaurants(): BelongsToMany
{
    return $this->belongsToMany(Restaurant::class, 'staff', 'user_id', 'restaurant_id')
                ->withPivot('id', 'shift_type', 'deleted_at')
                ->withTimestamps();
}

    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organizations::class, 'owner_id');
    }

    public function ownedRestaurants(): HasManyThrough
    {
        return $this->hasManyThrough(Restaurant::class, Organizations::class, 'owner_id', 'organization_id');
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    
    public function isSuperAdmin(): bool
{
 
    return $this->roles()->where('slug', 'super_admin')->exists();
}
}