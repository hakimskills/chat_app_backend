<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

trait EnsuresConversationParticipant
{
    /**
     * Confirm the given user is an active participant in the conversation,
     * aborting with 403 if not. Returns the participant pivot row so
     * callers can update it (e.g. the read cursor) without a second query.
     */
    protected function authorizeParticipant(Conversation $conversation, User $user): ConversationParticipant
    {
        $participant = $conversation->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();

        abort_if(! $participant, 403, 'You are not a participant in this conversation.');

        return $participant;
    }
}