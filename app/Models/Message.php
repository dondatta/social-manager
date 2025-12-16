<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'instagram_user_id',
        'instagram_username',
        'profile_picture_url',
        'message_type',
        'message_text',
        'media_id',
        'comment_id',
        'raw_payload',
        'synced_to_hubspot',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'synced_to_hubspot' => 'boolean',
    ];
}
