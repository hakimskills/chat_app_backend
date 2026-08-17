<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\ConversationParticipant
 */
class ParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user->id,
            'name' => $this->user->name,
            'username' => $this->user->username,
            'avatar' => $this->user->avatar ? Storage::disk('public')->url($this->user->avatar) : null,
            'role' => $this->role,
            'is_admin' => $this->isAdmin(),
            'joined_at' => $this->joined_at,
        ];
    }
}