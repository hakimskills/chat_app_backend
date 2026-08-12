<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Search users by name or username (case-insensitive partial match),
     * excluding the authenticated user. Each result includes its
     * friendship_status relative to the authenticated user so the client
     * can show the right action button (Add / Pending / Accept / Message)
     * without a second round trip.
     */
    public function search(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'q' => ['required', 'string', 'min:1', 'max:50'],
        ])->validate();

        $query = trim($request->string('q'));
        $authUser = $request->user();

        $users = User::query()
            ->where('id', '!=', $authUser->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'ilike', "%{$query}%")
                  ->orWhere('username', 'ilike', "%{$query}%");
            })
            ->limit(20)
            ->get();

        if ($users->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $pairKeys = $users->map(fn (User $u) => Friendship::pairKey($authUser->id, $u->id));
        $friendships = Friendship::whereIn('pair_key', $pairKeys)->get()->keyBy('pair_key');

        $data = $users->map(function (User $u) use ($authUser, $friendships) {
            $pairKey = Friendship::pairKey($authUser->id, $u->id);
            $friendship = $friendships->get($pairKey);

            $status = 'none';
            $friendshipId = null;

            if ($friendship) {
                $friendshipId = $friendship->id;

                $status = match ($friendship->status) {
                    'accepted' => 'friends',
                    'blocked' => 'blocked',
                    'pending' => $friendship->sender_id === $authUser->id
                        ? 'pending_sent'
                        : 'pending_incoming',
                    default => 'none',
                };
            }

            return [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'avatar' => $u->avatar ? Storage::disk('public')->url($u->avatar) : null,
                'friendship_status' => $status,
                'friendship_id' => $friendshipId,
            ];
        });

        return response()->json(['data' => $data]);
    }
}