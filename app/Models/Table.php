<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Table extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'restaurant_id',
        'table_number',
        'name',
        'slug',
        'token',
        'capacity',
        'qr_code',
        'status',
        'is_active',
    ];

    protected $casts = [
        'table_number' => 'integer',
        'capacity'     => 'integer',
        'is_active'    => 'boolean',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}