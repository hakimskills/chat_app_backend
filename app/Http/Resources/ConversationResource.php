<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authId = $request->user()->id;

        // For a private chat, the "display name/avatar" is the OTHER
        // person — group chats use their own name/avatar directly.
        $displayName = $this->name;
        $displayAvatar = $this->avatar;

        if ($this->isPrivate() && $this->relationLoaded('users')) {
            $other = $this->users->firstWhere('id', '!=', $authId);
            $displayName = $other?->name;
            $displayAvatar = $other?->avatar;
        }

        $participant = $this->relationLoaded('participants')
            ? $this->participants->firstWhere('user_id', $authId)
            : null;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $displayName,
            'avatar' => $displayAvatar,
            'last_message' => $this->whenLoaded(
                'lastMessage',
                fn () => $this->lastMessage ? new MessageResource($this->lastMessage) : null
            ),
            'unread_count' => $participant?->unreadCount() ?? 0,
            'updated_at' => $this->updated_at,

            // Only send the full participant list for groups — private
            // chats already convey the other person via name/avatar above.
            'participants' => $this->when(
                $this->isGroup() && $this->relationLoaded('users'),
                fn () => $this->users->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username,
                    'avatar' => $u->avatar,
                ])
            ),
        ];
    }
}