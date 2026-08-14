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
    public function index(Request $request): AnonymousResourceCollection
    {
        $friendships = Friendship::involving($request->user()->id)
            ->accepted()
            ->with(['sender', 'recipient'])
            ->get();

        return FriendshipResource::collection($friendships);
    }

    public function incomingRequests(Request $request): AnonymousResourceCollection
    {
        $friendships = Friendship::where('recipient_id', $request->user()->id)
            ->pending()
            ->with(['sender', 'recipient'])
            ->latest()
            ->get();

        return FriendshipResource::collection($friendships);
    }

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
     * Users the authenticated user has blocked. block() always stores
     * the blocker as sender_id, so this is a simple, unambiguous query —
     * it never shows people who blocked YOU, only people YOU blocked.
     */
    public function blockedUsers(Request $request): AnonymousResourceCollection
    {
        $friendships = Friendship::where('sender_id', $request->user()->id)
            ->where('status', 'blocked')
            ->with(['sender', 'recipient'])
            ->latest()
            ->get();

        return FriendshipResource::collection($friendships);
    }

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

            if ($existing->sender_id === $authUser->id) {
                throw ValidationException::withMessages([
                    'recipient_id' => ['Friend request already sent.'],
                ]);
            }

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