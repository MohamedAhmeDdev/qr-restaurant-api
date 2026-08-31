<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Restaurant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'organization_id', 'is_active', 'status', 'logo'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organizations::class, 'organization_id');
    }


    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'staff')
                    ->withPivot('status', 'shift_type')
                    ->withTimestamps();
    }

    /**
     * Direct access to staff pivot records.
     */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function tables(): HasMany
{
    return $this->hasMany(Table::class);
}
}