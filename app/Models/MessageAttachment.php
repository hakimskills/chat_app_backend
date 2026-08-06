<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'url',
        'type',
        'file_name',
        'mime_type',
        'size_bytes',
        'thumbnail_url',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}