<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModifierGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'restaurant_id',
        'name',
        'description',
        'min_select',
        'max_select',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'is_required' => 'boolean',
        'min_select'  => 'integer',
        'max_select'  => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ModifierGroup $group) {
            if ($group->isForceDeleting()) {
                $group->options()->forceDelete();
            } else {
                $group->options()->delete();
            }
        });

        static::restored(function (ModifierGroup $group) {
            $group->options()->onlyTrashed()->restore();
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class);
    }

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_modifier_group');
    }
}