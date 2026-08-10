<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Friendship extends Model
{
    protected $fillable = [
        'sender_id',
        'recipient_id',
        'status',
        'pair_key',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public static function pairKey(int $userIdA, int $userIdB): string
    {
        $ids = [$userIdA, $userIdB];
        sort($ids);

        return implode('_', $ids);
    }

    /**
     * Given the authenticated user's id, return whichever side of this
     * friendship is the OTHER person.
     */
    public function otherUser(int $authUserId): User
    {
        return $this->sender_id === $authUserId ? $this->recipient : $this->sender;
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', 'accepted');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeInvolving(Builder $query, int $userId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('sender_id', $userId)
            ->orWhere('recipient_id', $userId));
    }
}