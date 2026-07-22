<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Str;

class UsernameGenerator
{
    /**
     * Derive a unique, URL-safe username from an email address (or any
     * seed string). Falls back to a random suffix on collision.
     *
     * Examples:
     *   jane.doe@example.com  -> janedoe
     *   janedoe (taken)       -> janedoe4821
     */
    public function generate(string $seed): string
    {
        $localPart = Str::contains($seed, '@') ? Str::before($seed, '@') : $seed;

        $base = Str::of($localPart)
            ->slug('')
            ->lower()
            ->limit(20, '')
            ->toString();

        $base = $base !== '' ? $base : 'user';

        $username = $base;

        while (User::where('username', $username)->exists()) {
            $username = $base.random_int(1000, 9999);
        }

        return $username;
    }
}
