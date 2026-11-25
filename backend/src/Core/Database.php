<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Single shared PDO connection.
 * Reads DATABASE_URL (postgres://user:pass@host:port/db, used by most hosts)
 * or the separate DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASSWORD variables.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect();
        }
        return self::$pdo;
    }

    /** Used by tests to swap the connection (e.g. after switching databases). */
    public static function reset(): void
    {
        self::$pdo = null;
    }

    private static function connect(): PDO
    {
        $url = getenv('DATABASE_URL');
        if ($url) {
            $p = parse_url($url);
            $host = $p['host'] ?? 'localhost';
            $port = $p['port'] ?? 5432;
            $name = ltrim($p['path'] ?? '', '/');
            $user = urldecode($p['user'] ?? '');
            $pass = urldecode($p['pass'] ?? '');
            parse_str($p['query'] ?? '', $query);
            $sslmode = $query['sslmode'] ?? getenv('DB_SSLMODE') ?: 'prefer';
        } else {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '5432';
            $name = getenv('DB_NAME') ?: 'keyvault';
            $user = getenv('DB_USER') ?: 'keyvault';
            $pass = getenv('DB_PASSWORD') ?: '';
            $sslmode = getenv('DB_SSLMODE') ?: 'prefer';
        }

        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$name;sslmode=$sslmode", $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    }

    /**
     * Runs $fn inside a transaction; commits on success, rolls back on any exception.
     * @template T
     * @param callable(PDO):T $fn
     * @return T
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
