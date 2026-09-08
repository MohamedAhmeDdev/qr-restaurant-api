<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organizations extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'owner_id', 'is_active','restore_token','restore_token_expires_at'];

    protected static function booted(): void
    {
        static::deleting(function (Organizations $organization) {
            if ($organization->isForceDeleting()) {
                $organization->restaurants()->forceDelete();
            } else {
                $organization->restaurants()->delete();
            }
        });

        static::restored(function (Organizations $organization) {
            $organization->restaurants()->onlyTrashed()->restore();
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function restaurants(): HasMany
    {
        return $this->hasMany(Restaurant::class, 'organization_id');
    }
}