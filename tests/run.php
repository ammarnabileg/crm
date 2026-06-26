<?php

declare(strict_types=1);

/**
 * Zero-dependency test runner.
 *
 *   php tests/run.php [filter]
 *
 * Discovers tests/{Unit,Feature,Security}/*Test.php — each returns an anonymous
 * class extending Tests\TestCase — runs every public test* method, wrapping
 * DB-backed tests in a rolled-back transaction, and prints a summary. Exits
 * non-zero if anything fails, so it works in CI.
 */

use App\Core\Database;
use Tests\AssertionFailed;
use Tests\TestCase;

$app = require __DIR__ . '/bootstrap.php';

$filter = $argv[1] ?? '';
$dirs = ['Unit', 'Feature', 'Security'];

$files = [];
foreach ($dirs as $dir) {
    foreach (glob(__DIR__ . '/' . $dir . '/*Test.php') ?: [] as $file) {
        if ($filter === '' || str_contains(strtolower($file), strtolower($filter))) {
            $files[] = $file;
        }
    }
}
sort($files);

$passed = 0;
$failed = 0;
$errors = 0;
$failures = [];
$start = microtime(true);

echo "HalaOps test runner\n";
echo str_repeat('=', 60) . "\n";

foreach ($files as $file) {
    $case = require $file;
    if (! $case instanceof TestCase) {
        echo "  SKIP (not a TestCase): " . basename($file) . "\n";
        continue;
    }

    $name = basename($file, '.php');
    $methods = array_filter(
        get_class_methods($case),
        static fn (string $m): bool => str_starts_with($m, 'test')
    );

    echo "\n" . $name . "\n";

    foreach ($methods as $method) {
        $usesTx = $case->usesDatabaseTransaction();
        /** @var Database $db */
        $db = $usesTx ? app('db') : null;

        try {
            if ($usesTx) {
                $db->beginTransaction();
            }
            $case->setUp();
            $case->{$method}();
            $case->tearDown();
            if ($usesTx) {
                $db->rollBack();
            }
            $passed++;
            echo "  \033[32m✓\033[0m {$method}\n";
        } catch (AssertionFailed $e) {
            if ($usesTx && $db->inTransactionDepth() > 0) {
                $db->rollBack();
            }
            $failed++;
            $failures[] = "{$name}::{$method} — " . $e->getMessage();
            echo "  \033[31m✗ {$method} — " . $e->getMessage() . "\033[0m\n";
        } catch (Throwable $e) {
            if ($usesTx && $db->inTransactionDepth() > 0) {
                $db->rollBack();
            }
            $errors++;
            $failures[] = "{$name}::{$method} — ERROR: " . $e->getMessage()
                . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
            echo "  \033[31m! {$method} — ERROR: " . $e->getMessage() . "\033[0m\n";
        }
    }
}

$duration = round((microtime(true) - $start) * 1000);
echo "\n" . str_repeat('=', 60) . "\n";
if ($failures !== []) {
    echo "\nFAILURES:\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
}
echo sprintf(
    "\n%d passed, %d failed, %d errored — %d assertions in %dms\n",
    $passed,
    $failed,
    $errors,
    TestCase::$assertions,
    $duration
);

exit($failed + $errors > 0 ? 1 : 0);
