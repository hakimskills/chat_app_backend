<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFriendRequestRequest;
use App\Http\Resources\FriendshipResource;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class FriendshipController extends Controller
{
    /**
     * Accepted friends only.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $friendships = Friendship::involving($request->user()->id)
            ->accepted()
            ->with(['sender', 'recipient'])
            ->get();

        return FriendshipResource::collection($friendships);
    }

    /**
     * Pending requests sent TO the authenticated user (need a response).
     */
    public function incomingRequests(Request $request): AnonymousResourceCollection
    {
        $friendships = Friendship::where('recipient_id', $request->user()->id)
            ->pending()
            ->with(['sender', 'recipient'])
            ->latest()
            ->get();

        return FriendshipResource::collection($friendships);
    }

    /**
     * Pending requests the authenticated user has sent, awaiting a response.
     */
    public function sentRequests(Request $request): AnonymousResourceCollection
    {
        $friendships = Friendship::where('sender_id', $request->user()->id)
            ->pending()
            ->with(['sender', 'recipient'])
            ->latest()
            ->get();

        return FriendshipResource::collection($friendships);
    }

    /**
     * Send a friend request by user_id or username. If the target user
     * already sent *us* a pending request, this auto-accepts it instead
     * of creating a duplicate.
     */
    public function store(StoreFriendRequestRequest $request): JsonResponse
    {
        $authUser = $request->user();

        $recipient = $request->filled('recipient_id')
            ? User::findOrFail($request->integer('recipient_id'))
            : User::where('username', $request->string('username'))->firstOrFail();

        if ($recipient->id === $authUser->id) {
            throw ValidationException::withMessages([
                'recipient_id' => ['You cannot send a friend request to yourself.'],
            ]);
        }

        $pairKey = Friendship::pairKey($authUser->id, $recipient->id);
        $existing = Friendship::where('pair_key', $pairKey)->first();

        if ($existing) {
            if ($existing->status === 'accepted') {
                throw ValidationException::withMessages([
                    'recipient_id' => ['You are already friends with this user.'],
                ]);
            }

            if ($existing->status === 'blocked') {
                abort(403, 'This action is not allowed.');
            }

            // Existing status is 'pending'.
            if ($existing->sender_id === $authUser->id) {
                throw ValidationException::withMessages([
                    'recipient_id' => ['Friend request already sent.'],
                ]);
            }

            // They already requested us — sending our own request now
            // just accepts theirs, avoiding a duplicate row.
            $existing->update(['status' => 'accepted']);

            return response()->json([
                'friendship' => new FriendshipResource($existing->fresh(['sender', 'recipient'])),
            ]);
        }

        $friendship = Friendship::create([
            'sender_id' => $authUser->id,
            'recipient_id' => $recipient->id,
            'status' => 'pending',
            'pair_key' => $pairKey,
        ]);

        return response()->json([
            'friendship' => new FriendshipResource($friendship->fresh(['sender', 'recipient'])),
        ], 201);
    }

    /**
     * Accept an incoming pending request. Only the recipient may accept.
     */
    public function accept(Request $request, Friendship $friendship): JsonResponse
    {
        $authId = $request->user()->id;

        abort_if($friendship->recipient_id !== $authId, 403);
        abort_if($friendship->status !== 'pending', 422, 'This request is no longer pending.');

        $friendship->update(['status' => 'accepted']);

        return response()->json([
            'friendship' => new FriendshipResource($friendship->fresh(['sender', 'recipient'])),
        ]);
    }

    /**
     * Decline an incoming request, or cancel one you sent — either the
     * sender or the recipient of a still-pending request may remove it.
     * The row is deleted so a fresh request can be sent again later.
     */
    public function destroyRequest(Request $request, Friendship $friendship): JsonResponse
    {
        $authId = $request->user()->id;

        abort_if(
            $friendship->sender_id !== $authId && $friendship->recipient_id !== $authId,
            403
        );
        abort_if($friendship->status !== 'pending', 422, 'This request is no longer pending.');

        $friendship->delete();

        return response()->json(['message' => 'Request removed.']);
    }

    /**
     * Unfriend — removes an accepted friendship. Either party can do this.
     */
    public function destroy(Request $request, Friendship $friendship): JsonResponse
    {
        $authId = $request->user()->id;

        abort_if(
            $friendship->sender_id !== $authId && $friendship->recipient_id !== $authId,
            403
        );
        abort_if($friendship->status !== 'accepted', 422, 'You are not friends with this user.');

        $friendship->delete();

        return response()->json(['message' => 'Friend removed.']);
    }

    /**
     * Block a user — find-or-create the pair row and force it to
     * 'blocked'. Only the blocker (sender_id) can later unblock, so a
     * blocked user can't just re-request or unblock themselves.
     */
    public function block(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();

        abort_if($user->id === $authUser->id, 422, 'You cannot block yourself.');

        $pairKey = Friendship::pairKey($authUser->id, $user->id);
        $friendship = Friendship::where('pair_key', $pairKey)->first();

        if ($friendship) {
            $friendship->update([
                'sender_id' => $authUser->id,
                'recipient_id' => $user->id,
                'status' => 'blocked',
            ]);
        } else {
            $friendship = Friendship::create([
                'sender_id' => $authUser->id,
                'recipient_id' => $user->id,
                'status' => 'blocked',
                'pair_key' => $pairKey,
            ]);
        }

        return response()->json(['message' => 'User blocked.']);
    }

    public function unblock(Request $request, Friendship $friendship): JsonResponse
    {
        $authId = $request->user()->id;

        abort_if($friendship->status !== 'blocked', 422, 'This user is not blocked.');
        abort_if($friendship->sender_id !== $authId, 403, 'Only the person who blocked can unblock.');

        $friendship->delete();

        return response()->json(['message' => 'User unblocked.']);
    }
}