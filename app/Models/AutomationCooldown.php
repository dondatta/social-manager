<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationCooldown extends Model
{
    protected $fillable = [
        'instagram_user_id',
        'action_type',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
