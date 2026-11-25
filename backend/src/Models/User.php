<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    /** Never returns password_hash to callers outside this class. */
    public static function toPublic(array $row): array
    {
        return [
            'id'         => (int) $row['id'],
            'name'       => $row['name'],
            'email'      => $row['email'],
            'created_at' => $row['created_at'],
        ];
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?)');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name, string $email, string $password): array
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?) RETURNING *'
        );
        $stmt->execute([$name, strtolower($email), password_hash($password, PASSWORD_DEFAULT)]);
        return $stmt->fetch();
    }

    public static function findByTokenHash(string $hash): ?array
    {
        $stmt = Database::connection()->prepare(
            'UPDATE api_tokens SET last_used_at = NOW() WHERE token_hash = ?
             RETURNING (SELECT row_to_json(u) FROM users u WHERE u.id = api_tokens.user_id) AS user'
        );
        $stmt->execute([$hash]);
        $json = $stmt->fetchColumn();
        return $json ? json_decode($json, true) : null;
    }

    public static function storeToken(int $userId, string $hash): void
    {
        Database::connection()
            ->prepare('INSERT INTO api_tokens (user_id, token_hash) VALUES (?, ?)')
            ->execute([$userId, $hash]);
    }

    public static function deleteToken(string $hash): void
    {
        Database::connection()->prepare('DELETE FROM api_tokens WHERE token_hash = ?')->execute([$hash]);
    }
}
