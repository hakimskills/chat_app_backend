<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Represents a friendship FROM the authenticated user's point of view —
 * always exposes "the other person," never "sender/recipient," so the
 * client doesn't have to figure out which side it's on.
 */
class FriendshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authId = $request->user()->id;
        $other = $this->otherUser($authId);

        return [
            'friendship_id' => $this->id,
            'status' => $this->status,
            'is_incoming' => $this->recipient_id === $authId && $this->status === 'pending',
            'user' => [
                'id' => $other->id,
                'name' => $other->name,
                'username' => $other->username,
                'avatar' => $other->avatar ? Storage::disk('public')->url($other->avatar) : null,
            ],
            'created_at' => $this->created_at,
        ];
    }
}