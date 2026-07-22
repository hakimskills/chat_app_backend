<?php

namespace App\Services\Auth;

use Google\Client as GoogleClient;
use RuntimeException;

/**
 * Wraps google/apiclient's ID token verification. This validates the
 * token's signature against Google's rotating public certs, checks
 * expiry, and checks the audience (client_id) — all the parts you do
 * NOT want to hand-roll.
 *
 * Install: composer require google/apiclient
 */
class GoogleTokenVerifier
{
    /**
     * @return array{sub: string, email?: string, email_verified?: bool, name?: string, picture?: string}
     */
    public function verify(string $idToken): array
    {
        $client = new GoogleClient([
            'client_id' => config('services.google.client_id'),
        ]);

        $payload = $client->verifyIdToken($idToken);

        if (! $payload) {
            throw new RuntimeException('Invalid or expired Google ID token.');
        }

        return $payload;
    }
}
