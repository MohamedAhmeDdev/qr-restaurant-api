<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $fillable = [
        'email',
        'organization_id',
        'restaurant_id',
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

    // Append virtual 'status' attribute automatically in JSON/Array representations
    protected $appends = ['status'];

    /**
     * Relationship to Organization
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organizations::class, 'organization_id');
    }

    /**
     * Relationship to Restaurant
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Relationship to Role assigned in invitation
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Relationship to the User who sent the invite
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Virtual computed 'status' attribute: accepted | expired | pending
     */
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