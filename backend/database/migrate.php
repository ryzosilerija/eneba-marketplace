<?php
declare(strict_types=1);

/**
 * Database setup script.
 *
 *   php database/migrate.php            run migrations that haven't run yet
 *   php database/migrate.php --fresh    drop everything and re-create the schema
 *   php database/migrate.php --seed     also insert sample data (use with --fresh)
 *   php database/migrate.php --seed-if-empty   seed only when there are no products yet
 *                                              (used on deploy so restarts never wipe real data)
 */
require __DIR__ . '/../src/bootstrap.php';

use App\Core\Database;

function migrate(bool $fresh, bool $seed, bool $quiet = false, bool $images = true): void
{
    $pdo = Database::connection();
    $log = fn (string $m) => $quiet ?: print($m . PHP_EOL);

    if ($fresh) {
        $pdo->exec('DROP SCHEMA public CASCADE; CREATE SCHEMA public;');
        $log('Dropped all tables.');
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(255) PRIMARY KEY, ran_at TIMESTAMPTZ NOT NULL DEFAULT NOW())');
    $done = $pdo->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

    $files = glob(__DIR__ . '/migrations/*.sql');
    sort($files);
    foreach ($files as $file) {
        $name = basename($file);
        if (in_array($name, $done, true)) {
            continue;
        }
        $pdo->exec(file_get_contents($file));
        $pdo->prepare('INSERT INTO migrations (name) VALUES (?)')->execute([$name]);
        $log("Migrated: $name");
    }

    if ($seed) {
        require_once __DIR__ . '/Seeder.php';
        (new Seeder($pdo, $images))->run($log);
    }
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $seed = in_array('--seed', $argv, true);
    if (in_array('--seed-if-empty', $argv, true)) {
        migrate(false, false);
        $seed = (int) Database::connection()->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0;
        echo $seed ? "Database is empty, seeding sample data.\n" : "Database already has data, skipping seed.\n";
    }
    migrate(in_array('--fresh', $argv, true), $seed);
}
