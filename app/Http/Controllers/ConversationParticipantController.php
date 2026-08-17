<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresConversationParticipant;
use App\Http\Requests\AddParticipantsRequest;
use App\Http\Requests\UpdateParticipantRoleRequest;
use App\Http\Resources\ParticipantResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ConversationParticipantController extends Controller
{
    use EnsuresConversationParticipant;

    /**
     * Active (not-left) members of a group, with their roles.
     */
    public function index(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        $this->authorizeParticipant($conversation, $request->user());
        $this->ensureGroup($conversation);

        $participants = $conversation->participants()
            ->whereNull('left_at')
            ->with('user')
            ->orderBy('joined_at')
            ->get();

        return ParticipantResource::collection($participants);
    }

    /**
     * Add one or more users to a group. Admins only. Re-adding someone
     * who previously left resets their left_at instead of erroring on
     * the unique constraint.
     */
    public function store(AddParticipantsRequest $request, Conversation $conversation): JsonResponse
    {
        $actor = $this->authorizeParticipant($conversation, $request->user());
        $this->ensureGroup($conversation);
        abort_unless($actor->isAdmin(), 403, 'Only admins can add members.');

        $userIds = $request->validated()['user_ids'];

        DB::transaction(function () use ($conversation, $userIds) {
            foreach ($userIds as $userId) {
                $conversation->participants()->updateOrCreate(
                    ['user_id' => $userId],
                    ['role' => 'member', 'left_at' => null, 'joined_at' => now()]
                );
            }
        });

        $participants = $conversation->participants()
            ->whereNull('left_at')
            ->with('user')
            ->orderBy('joined_at')
            ->get();

        return response()->json([
            'participants' => ParticipantResource::collection($participants),
        ], 201);
    }

    /**
     * Remove a participant. Two cases:
     *   - The authenticated user removes THEMSELVES -> "leave group".
     *     If they were the sole admin and others remain, the earliest
     *     remaining member is auto-promoted. If they were the last
     *     participant, the whole conversation is deleted.
     *   - An admin removes someone else -> "kick".
     */
    public function destroy(Request $request, Conversation $conversation, User $user): JsonResponse
    {
        $authUser = $request->user();
        $actor = $this->authorizeParticipant($conversation, $authUser);
        $this->ensureGroup($conversation);

        $isSelf = $user->id === $authUser->id;
        abort_unless($isSelf || $actor->isAdmin(), 403, 'Only admins can remove other members.');

        $target = $conversation->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();

        abort_if(! $target, 404, 'This user is not in the group.');

        DB::transaction(function () use ($conversation, $target) {
            $target->update(['left_at' => now()]);

            $remaining = $conversation->participants()
                ->whereNull('left_at')
                ->orderBy('joined_at')
                ->get();

            if ($remaining->isEmpty()) {
                // Nobody left — delete the group entirely (cascades to
                // messages/participants via FK constraints).
                $conversation->delete();
                return;
            }

            $hasAdmin = $remaining->contains(fn ($p) => $p->role === 'admin');

            if (! $hasAdmin) {
                // The departing member was the only admin — promote the
                // longest-standing remaining member so the group isn't
                // left without one.
                $remaining->first()->update(['role' => 'admin']);
            }
        });

        return response()->json([
            'message' => $isSelf ? 'You left the group.' : 'Member removed.',
        ]);
    }

    /**
     * Promote/demote a participant. Admins only. Refuses to demote the
     * last remaining admin — a group must always have at least one.
     */
    public function updateRole(
        UpdateParticipantRoleRequest $request,
        Conversation $conversation,
        User $user
    ): JsonResponse {
        $actor = $this->authorizeParticipant($conversation, $request->user());
        $this->ensureGroup($conversation);
        abort_unless($actor->isAdmin(), 403, 'Only admins can change roles.');

        $target = $conversation->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();

        abort_if(! $target, 404, 'This user is not in the group.');

        $newRole = $request->validated()['role'];

        if ($newRole === 'member' && $target->role === 'admin') {
            $adminCount = $conversation->participants()
                ->whereNull('left_at')
                ->where('role', 'admin')
                ->count();

            abort_if($adminCount <= 1, 422, 'A group must have at least one admin.');
        }

        $target->update(['role' => $newRole]);

        return response()->json([
            'participant' => new ParticipantResource($target->fresh('user')),
        ]);
    }

    private function ensureGroup(Conversation $conversation): void
    {
        abort_unless($conversation->isGroup(), 422, 'This action only applies to group conversations.');
    }
}