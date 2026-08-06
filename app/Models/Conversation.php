<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'avatar',
        'created_by',
        'direct_key',
        'last_message_id',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * The actual users in this conversation, with pivot columns exposed
     * (role, joined_at, last_read_message_id, etc.) — use this when you
     * need user data joined in, use participants() when you need the
     * pivot row itself (e.g. to update a read cursor).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['role', 'joined_at', 'left_at', 'muted_until', 'last_read_message_id'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function isGroup(): bool
    {
        return $this->type === 'group';
    }

    public function isPrivate(): bool
    {
        return $this->type === 'private';
    }

    /**
     * Deterministic key for a private conversation between two users,
     * regardless of argument order — e.g. directKey(3, 17) and
     * directKey(17, 3) both return "3_17". Used to enforce one private
     * conversation per user pair via the unique direct_key column.
     */
    public static function directKey(int $userIdA, int $userIdB): string
    {
        $ids = [$userIdA, $userIdB];
        sort($ids);

        return implode('_', $ids);
    }

    /**
     * Scope: conversations the given user is currently an active
     * participant in (excludes ones they've left).
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->whereHas('participants', function (Builder $q) use ($userId) {
            $q->where('user_id', $userId)->whereNull('left_at');
        });
    }
}