<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authId = $request->user()->id;

        // For a private chat, the "display name/avatar" is the OTHER
        // person — group chats use their own name/avatar directly.
        $displayName = $this->name;
        $displayAvatar = $this->resolveAvatarUrl($this->avatar);

        if ($this->isPrivate() && $this->relationLoaded('users')) {
            $other = $this->users->firstWhere('id', '!=', $authId);
            $displayName = $other?->name;
            $displayAvatar = $this->resolveAvatarUrl($other?->avatar);
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

            'participants' => $this->when(
                $this->isGroup() && $this->relationLoaded('users'),
                fn () => $this->users->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username,
                    'avatar' => $this->resolveAvatarUrl($u->avatar),
                ])
            ),
        ];
    }

    /**
     * Convert a stored relative path (e.g. "avatars/2/xyz.jpg") into a
     * full, absolute URL the client can actually load. Returns null
     * untouched — a user/group with no avatar stays null.
     */
    private function resolveAvatarUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}