<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Token authentication. Clients send "Authorization: Bearer <token>".
 * Tokens are random 40-byte hex strings; only their SHA-256 hash is stored in api_tokens,
 * so a leaked database dump doesn't leak usable tokens.
 */
final class Auth
{
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function userFromRequest(Request $request): ?array
    {
        $token = $request->bearerToken();
        if ($token === null) {
            return null;
        }
        return User::findByTokenHash(self::hashToken($token));
    }
}
