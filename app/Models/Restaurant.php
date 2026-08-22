<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'organization_id', 'is_active', 'status', 'logo'];

    /**
     * Get the parent organization that owns this restaurant location.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organizations::class);
    }

    /**
     * Get users assigned to this specific restaurant via user_roles.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
                    ->withPivot('role_id')
                    ->withTimestamps();
    }
}