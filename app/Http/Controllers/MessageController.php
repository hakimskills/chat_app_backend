<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresConversationParticipant;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    use EnsuresConversationParticipant;

    public function index(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        $this->authorizeParticipant($conversation, $request->user());

        $query = $conversation->messages()
            ->with(['sender', 'attachments', 'replyTo.sender'])
            ->orderByDesc('id');

        if ($request->filled('before')) {
            $query->where('id', '<', $request->integer('before'));
        }

        $messages = $query->limit(30)->get()->reverse()->values();

        return MessageResource::collection($messages);
    }

    /**
     * Handles plain text, image, and now audio (voice) messages —
     * whichever of 'body' / 'image' / 'audio' is present in the request.
     */
    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $this->authorizeParticipant($conversation, $user);

        $validated = $request->validated();
        $hasImage = $request->hasFile('image');
        $hasAudio = $request->hasFile('audio');

        $type = $hasAudio ? 'audio' : ($hasImage ? 'image' : 'text');

        $message = DB::transaction(function () use ($conversation, $user, $validated, $request, $hasImage, $hasAudio, $type) {
            $message = $conversation->messages()->create([
                'sender_id' => $user->id,
                'type' => $type,
                'body' => $validated['body'] ?? null,
                'reply_to_message_id' => $validated['reply_to_message_id'] ?? null,
            ]);

            if ($hasImage) {
                $file = $request->file('image');
                $path = $file->store("message_attachments/{$conversation->id}", 'public');

                $message->attachments()->create([
                    'url' => $path,
                    'type' => 'image',
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize(),
                ]);
            }

            if ($hasAudio) {
                $file = $request->file('audio');
                $path = $file->store("message_attachments/{$conversation->id}", 'public');

                $message->attachments()->create([
                    'url' => $path,
                    'type' => 'audio',
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize(),
                    'duration_seconds' => $validated['duration_seconds'] ?? null,
                ]);
            }

            $conversation->update(['last_message_id' => $message->id]);

            $conversation->participants()
                ->where('user_id', $user->id)
                ->update(['last_read_message_id' => $message->id]);

            return $message;
        });

        $message->load(['sender', 'replyTo.sender', 'attachments']);

        return response()->json([
            'message_data' => new MessageResource($message),
        ], 201);
    }

    public function update(UpdateMessageRequest $request, Conversation $conversation, Message $message): JsonResponse
    {
        $user = $request->user();
        $this->authorizeParticipant($conversation, $user);

        abort_if($message->conversation_id !== $conversation->id, 404);
        abort_if($message->sender_id !== $user->id, 403, 'You can only edit your own messages.');

        $message->update([
            'body' => $request->validated()['body'],
            'edited_at' => now(),
        ]);

        return response()->json([
            'message_data' => new MessageResource($message->fresh(['sender', 'replyTo.sender', 'attachments'])),
        ]);
    }

    public function destroy(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $user = $request->user();
        $this->authorizeParticipant($conversation, $user);

        abort_if($message->conversation_id !== $conversation->id, 404);
        abort_if($message->sender_id !== $user->id, 403, 'You can only delete your own messages.');

        $message->delete();

        return response()->json(['message' => 'Message deleted.']);
    }
}