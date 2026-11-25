<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Models\User;

final class AuthService
{
    /** @return array{user: array, token: string} */
    public function register(string $name, string $email, string $password, ?string $guestCartToken): array
    {
        if (User::findByEmail($email)) {
            throw HttpException::validation(['email' => ['This email is already registered.']]);
        }
        $user = User::create($name, $email, $password);
        return $this->issueToken($user, $guestCartToken);
    }

    /** @return array{user: array, token: string} */
    public function login(string $email, string $password, ?string $guestCartToken): array
    {
        $user = User::findByEmail($email);
        // Same message for "no such user" and "wrong password" so attackers can't probe which emails exist.
        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new HttpException(401, 'Invalid email or password.');
        }
        return $this->issueToken($user, $guestCartToken);
    }

    public function logout(string $token): void
    {
        User::deleteToken(Auth::hashToken($token));
    }

    private function issueToken(array $user, ?string $guestCartToken): array
    {
        $token = bin2hex(random_bytes(40));
        User::storeToken((int) $user['id'], Auth::hashToken($token));

        // Items added before logging in are moved into the user's cart.
        if ($guestCartToken) {
            (new CartService())->mergeGuestCart($guestCartToken, (int) $user['id']);
        }
        return ['user' => User::toPublic($user), 'token' => $token];
    }
}
