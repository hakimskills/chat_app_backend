<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleLoginRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\AuthProvider;
use App\Models\User;
use App\Services\Auth\GoogleTokenVerifier;
use App\Services\Auth\UsernameGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly UsernameGenerator $usernameGenerator,
        private readonly GoogleTokenVerifier $googleTokenVerifier,
    ) {}

    /**
     * Register with email & password. User is logged in immediately —
     * response includes a ready-to-use access token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'username' => $this->usernameGenerator->generate($validated['email']),
            'email' => $validated['email'],
            'password' => $validated['password'], // auto-hashed by the User model's cast
        ]);

        $token = $user->createToken($validated['device_name'] ?? 'default-device')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Login with email & password. Does NOT revoke tokens from other
     * devices — each login simply issues a new, independent token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        // $user->password may be null for OAuth-only accounts — Hash::check
        // would throw on null, so we short-circuit that case explicitly.
        if (! $user || ! $user->password || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken($validated['device_name'] ?? 'default-device')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Single endpoint for both Google login and Google registration.
     *
     * Resolution order:
     *   1. auth_providers already links this Google account -> log in as that user.
     *   2. No link, but a user with this email exists -> link Google to that account.
     *   3. Neither -> create a brand new user + provider link.
     */
    public function google(GoogleLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $payload = $this->googleTokenVerifier->verify($validated['id_token']);

        $googleId = $payload['sub'];
        $email = $payload['email'] ?? null;
        $emailVerified = (bool) ($payload['email_verified'] ?? false);
        $name = $payload['name'] ?? ($email ? Str::before($email, '@') : 'Google User');
        $avatar = $payload['picture'] ?? null;

        $user = DB::transaction(function () use ($googleId, $email, $emailVerified, $name, $avatar) {
            $existingLink = AuthProvider::where('provider', 'google')
                ->where('provider_user_id', $googleId)
                ->first();

            if ($existingLink) {
                return $existingLink->user;
            }

            $user = $email ? User::where('email', $email)->first() : null;

            if (! $user) {
                $user = User::create([
                    'name' => $name,
                    'username' => $this->usernameGenerator->generate($email ?? $name),
                    'email' => $email,
                    'password' => null,
                    'email_verified_at' => $emailVerified ? now() : null,
                ]);
            } elseif ($emailVerified && ! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            AuthProvider::create([
                'user_id' => $user->id,
                'provider' => 'google',
                'provider_user_id' => $googleId,
                'avatar' => $avatar,
            ]);

            return $user;
        });

        $token = $user->createToken($validated['device_name'] ?? 'default-device')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Revoke ONLY the token used to authenticate this request. Tokens
     * belonging to other devices are left untouched.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }
}
