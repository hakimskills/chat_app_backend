<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresConversationParticipant;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConversationController extends Controller
{
    use EnsuresConversationParticipant;

    /**
     * All conversations the authenticated user is currently in,
     * most recently active first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $conversations = Conversation::forUser($request->user()->id)
            ->with(['users', 'participants', 'lastMessage.sender'])
            ->orderByDesc('updated_at')
            ->paginate(20);

        return ConversationResource::collection($conversations);
    }

    /**
     * Create a group conversation, or find-or-create the private
     * conversation between the authenticated user and another user.
     */
    public function store(StoreConversationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if ($validated['type'] === 'group') {
            $conversation = $this->createGroup($validated, $user->id);
        } else {
            if ((int) $validated['user_id'] === $user->id) {
                throw ValidationException::withMessages([
                    'user_id' => ['You cannot start a conversation with yourself.'],
                ]);
            }

            $conversation = $this->findOrCreatePrivate($user->id, (int) $validated['user_id']);
        }

        $conversation->load(['users', 'participants', 'lastMessage.sender']);

        return response()->json([
            'conversation' => new ConversationResource($conversation),
        ], 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($conversation, $request->user());

        $conversation->load(['users', 'participants', 'lastMessage.sender']);

        return response()->json([
            'conversation' => new ConversationResource($conversation),
        ]);
    }

    /**
     * Move the authenticated user's read cursor to the latest message —
     * powers the unread badge going to zero when a chat is opened.
     */
    public function markAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        $participant = $this->authorizeParticipant($conversation, $request->user());

        $latestMessageId = $conversation->messages()->max('id');

        $participant->update(['last_read_message_id' => $latestMessageId]);

        return response()->json(['message' => 'Marked as read.']);
    }

    private function createGroup(array $validated, int $creatorId): Conversation
    {
        return DB::transaction(function () use ($validated, $creatorId) {
            $conversation = Conversation::create([
                'type' => 'group',
                'name' => $validated['name'],
                'created_by' => $creatorId,
            ]);

            $participantIds = array_unique([...$validated['participant_ids'], $creatorId]);

            foreach ($participantIds as $id) {
                $conversation->participants()->create([
                    'user_id' => $id,
                    'role' => $id === $creatorId ? 'admin' : 'member',
                ]);
            }

            return $conversation;
        });
    }

    private function findOrCreatePrivate(int $userId, int $otherUserId): Conversation
    {
        $key = Conversation::directKey($userId, $otherUserId);

        $existing = Conversation::where('direct_key', $key)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($key, $userId, $otherUserId) {
            $conversation = Conversation::create([
                'type' => 'private',
                'direct_key' => $key,
                'created_by' => $userId,
            ]);

            $conversation->participants()->createMany([
                ['user_id' => $userId],
                ['user_id' => $otherUserId],
            ]);

            return $conversation;
        });
    }
}