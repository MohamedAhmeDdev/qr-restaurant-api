<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organizations extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'owner_id', 'is_active'];

    /**
     * Get the owner of the organization.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all restaurants under this organization.
     */
   public function restaurants(): HasMany
{
    return $this->hasMany(Restaurant::class, 'organization_id');
}
    
}