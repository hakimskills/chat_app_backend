<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Update fields on the authenticated user's own profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Permanently delete the authenticated user's account.
     *
     * If the account has a password, it must be re-confirmed here — this
     * stops a leaked/stolen token alone from being enough to destroy the
     * account. OAuth-only accounts (no password) skip this check since
     * there's nothing to confirm against.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->password) {
            Validator::make($request->all(), [
                'password' => ['required', 'string'],
            ])->validate();

            if (! Hash::check($request->input('password'), $user->password)) {
                throw ValidationException::withMessages([
                    'password' => ['The provided password is incorrect.'],
                ]);
            }
        }

        // Revoke every token, on every device — there's no account left
        // for any of them to authenticate against after this.
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully.',
        ]);
    }
}