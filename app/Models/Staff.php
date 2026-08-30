<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use SoftDeletes;

    protected $table = 'staff';

    protected $fillable = [
        'user_id',
        'restaurant_id',
        'status',
        'shift_type',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];
}