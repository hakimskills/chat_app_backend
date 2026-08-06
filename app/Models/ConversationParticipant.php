<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'joined_at',
        'left_at',
        'muted_until',
        'last_read_message_id',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'muted_until' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_read_message_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isMuted(): bool
    {
        return $this->muted_until !== null && $this->muted_until->isFuture();
    }

    /**
     * Count of messages in this conversation the participant hasn't
     * read yet — the number your Flutter "unread badge" UI will
     * eventually bind to instead of the mock data.
     */
    public function unreadCount(): int
    {
        $query = $this->conversation->messages()
            ->where('sender_id', '!=', $this->user_id);

        if ($this->last_read_message_id) {
            $query->where('id', '>', $this->last_read_message_id);
        }

        return $query->count();
    }
}