<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,

            'sender' => $this->whenLoaded('sender', fn () => $this->sender ? [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'username' => $this->sender->username,
                'avatar' => $this->sender->avatar ? Storage::disk('public')->url($this->sender->avatar) : null,
            ] : null),

            'is_mine' => $this->sender_id === $request->user()?->id,

            'type' => $this->type,
            'body' => $this->body,

            'reply_to' => $this->whenLoaded('replyTo', fn () => $this->replyTo ? [
                'id' => $this->replyTo->id,
                'body' => $this->replyTo->body,
                'sender_name' => $this->replyTo->sender?->name,
            ] : null),

            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => [
                'id' => $a->id,
                'url' => Storage::disk('public')->url($a->url),
                'type' => $a->type,
                'file_name' => $a->file_name,
                'thumbnail_url' => $a->thumbnail_url ? Storage::disk('public')->url($a->thumbnail_url) : null,
                'duration_seconds' => $a->duration_seconds,
            ])),

            'edited_at' => $this->edited_at,
            'created_at' => $this->created_at,
        ];
    }
}