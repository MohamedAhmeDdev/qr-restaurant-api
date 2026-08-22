<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $fillable = [
        'email',
        'organizations_id',
        'restaurant_id', // Add this if invitations can target specific restaurants
        'role_id',
        'token',
        'invited_by',
        'accepted_at',
        'expires_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Append virtual 'status' attribute automatically in array/JSON responses
    protected $appends = ['status'];

    /**
     * Relationship to Organization (Singular casing for standard Laravel conventions)
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organizations::class, 'organizations_id');
    }

    /**
     * Relationship to Restaurant (Optional depending on schema)
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    // Add this inside App\Models\Invitation

public function invitedBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'invited_by');
}



    protected function status(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->accepted_at !== null) {
                    return 'accepted';
                }

                if ($this->expires_at && $this->expires_at->isPast()) {
                    return 'expired';
                }

                return 'pending';
            }
        );
    }
}