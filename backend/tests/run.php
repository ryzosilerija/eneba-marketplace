<?php
declare(strict_types=1);

/**
 * Test runner:  php tests/run.php            (all tests)
 *               php tests/run.php Checkout   (only test classes whose name contains "Checkout")
 *
 * Finds tests/*Test.php, runs every public method starting with "test", prints a summary,
 * and exits with code 1 if anything failed (so CI can use it).
 */

// Point the app at the test database BEFORE anything connects.
require __DIR__ . '/../src/bootstrap.php';
$testDb = getenv('DB_TEST_NAME') ?: 'keyvault_test';
putenv('DATABASE_URL'); // ignore any production URL
putenv("DB_NAME=$testDb");

require __DIR__ . '/../database/migrate.php';
require __DIR__ . '/TestCase.php';

$filter = $argv[1] ?? '';
$passed = 0;
$failures = [];
$start = microtime(true);

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    require_once $file;
    $class = 'Tests\\' . basename($file, '.php');
    if ($filter && !str_contains($class, $filter)) {
        continue;
    }
    echo basename($file, '.php') . PHP_EOL;

    foreach (get_class_methods($class) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }
        $test = new $class();
        try {
            $test->setUp();
            $test->$method();
            $passed++;
            echo "  \033[32m✓\033[0m $method" . PHP_EOL;
        } catch (\Throwable $e) {
            $failures[] = "$class::$method — " . $e->getMessage() . ($e instanceof Tests\AssertionFailed ? '' : "\n" . $e->getTraceAsString());
            echo "  \033[31m✗\033[0m $method" . PHP_EOL;
        }
    }
}

printf(PHP_EOL . "%d passed, %d failed (%.1fs)" . PHP_EOL, $passed, count($failures), microtime(true) - $start);
foreach ($failures as $f) {
    echo PHP_EOL . "\033[31mFAIL\033[0m $f" . PHP_EOL;
}
exit($failures ? 1 : 0);
