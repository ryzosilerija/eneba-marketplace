<?php
declare(strict_types=1);

namespace Tests;

use App\App;
use App\Core\Database;
use App\Core\Request;

/**
 * Base class for tests. A tiny PHPUnit-like setup (we can't install PHPUnit without Composer).
 *
 * - Every test method runs against a freshly migrated + seeded TEST database (DB_TEST_NAME),
 *   so tests are independent and never touch your development data.
 * - Requests are sent straight into App::handle() — no web server needed — so tests exercise
 *   the real router, validation, services and SQL.
 */
abstract class TestCase
{
    protected App $app;

    public function setUp(): void
    {
        Database::reset();
        migrate(fresh: true, seed: true, quiet: true, images: false);
        $this->app = new App();
    }

    /** Sends a request and returns [status, decodedJson]. */
    protected function call(string $method, string $path, array $body = [], array $headers = []): array
    {
        $parts = parse_url($path);
        parse_str($parts['query'] ?? '', $query);
        $headers = array_change_key_case($headers, CASE_LOWER);
        $response = $this->app->handle(new Request($method, $parts['path'], $query, $body, $headers));
        return [$response->status, $response->data];
    }

    /** Registers a fresh user and returns its auth header. */
    protected function registerUser(string $email = 'tester@example.com'): array
    {
        [$status, $body] = $this->call('POST', '/api/auth/register', [
            'name' => 'Tester', 'email' => $email, 'password' => 'secret123',
        ]);
        $this->assertSame(201, $status, 'register failed');
        return ['Authorization' => 'Bearer ' . $body['token']];
    }

    protected function db(): \PDO
    {
        return Database::connection();
    }

    /** First in-stock offer of a product, by product id. */
    protected function inStockOffer(int $minStock = 1): array
    {
        return $this->db()->query(
            "SELECT o.id, o.product_id, COUNT(k.id) AS stock FROM offers o
             JOIN digital_keys k ON k.offer_id = o.id AND k.status = 'available'
             GROUP BY o.id HAVING COUNT(k.id) >= $minStock ORDER BY o.id LIMIT 1"
        )->fetch();
    }

    protected const VALID_CARD = ['card_number' => '4242 4242 4242 4242', 'card_name' => 'Test User', 'expiry' => '12/30', 'cvc' => '123'];

    // ---- assertions --------------------------------------------------------

    protected function assertSame(mixed $expected, mixed $actual, string $msg = ''): void
    {
        if ($expected !== $actual) {
            throw new AssertionFailed(($msg ? "$msg: " : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }

    protected function assertTrue(bool $condition, string $msg = 'expected true'): void
    {
        if (!$condition) {
            throw new AssertionFailed($msg);
        }
    }

    protected function assertContains(mixed $needle, array $haystack, string $msg = ''): void
    {
        if (!in_array($needle, $haystack, true)) {
            throw new AssertionFailed(($msg ? "$msg: " : '') . var_export($needle, true) . ' not found in [' . implode(', ', array_map('strval', $haystack)) . ']');
        }
    }
}

final class AssertionFailed extends \Exception
{
}
