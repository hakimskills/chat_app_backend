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

    /**
     * Paginated messages, oldest-to-newest within each page — pass
     * ?before=<message_id> to load older messages (infinite scroll up).
     */
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

    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $this->authorizeParticipant($conversation, $user);

        $validated = $request->validated();

        $message = DB::transaction(function () use ($conversation, $user, $validated) {
            $message = $conversation->messages()->create([
                'sender_id' => $user->id,
                'type' => $validated['type'] ?? 'text',
                'body' => $validated['body'],
                'reply_to_message_id' => $validated['reply_to_message_id'] ?? null,
            ]);

            $conversation->update(['last_message_id' => $message->id]);

            // The sender has, by definition, "read" their own message.
            $conversation->participants()
                ->where('user_id', $user->id)
                ->update(['last_read_message_id' => $message->id]);

            return $message;
        });

        $message->load(['sender', 'replyTo.sender']);

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
            'message_data' => new MessageResource($message->fresh(['sender', 'replyTo.sender'])),
        ]);
    }

    public function destroy(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $user = $request->user();
        $this->authorizeParticipant($conversation, $user);

        abort_if($message->conversation_id !== $conversation->id, 404);
        abort_if($message->sender_id !== $user->id, 403, 'You can only delete your own messages.');

        $message->delete(); // soft delete — see softDeletes() on the messages table

        return response()->json(['message' => 'Message deleted.']);
    }
}