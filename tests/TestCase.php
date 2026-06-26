<?php

declare(strict_types=1);

namespace Tests;

use Throwable;

/**
 * Base test case for the in-house, zero-dependency test runner.
 *
 * The platform ships with no runtime Composer dependencies, so tests use this
 * lightweight harness rather than PHPUnit (a dev-only PHPUnit setup remains a
 * documented alternative — see docs/39-Testing-Strategy.md). Subclasses define
 * public `test*` methods; assertions throw AssertionFailed on failure, which the
 * runner records per-test.
 */
abstract class TestCase
{
    /** When true, the runner wraps each test in a DB transaction and rolls back. */
    protected bool $useDatabaseTransaction = false;

    public static int $assertions = 0;

    public function usesDatabaseTransaction(): bool
    {
        return $this->useDatabaseTransaction;
    }

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    // --- Assertions --------------------------------------------------------

    protected function assertTrue(mixed $condition, string $message = ''): void
    {
        self::$assertions++;
        if ($condition !== true) {
            $this->failAssertion($message ?: 'Failed asserting that value is true.');
        }
    }

    protected function assertFalse(mixed $condition, string $message = ''): void
    {
        self::$assertions++;
        if ($condition !== false) {
            $this->failAssertion($message ?: 'Failed asserting that value is false.');
        }
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::$assertions++;
        if ($expected !== $actual) {
            $this->failAssertion($message ?: sprintf(
                'Failed asserting two values are identical. Expected %s, got %s.',
                $this->export($expected),
                $this->export($actual)
            ));
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::$assertions++;
        if ($expected != $actual) {
            $this->failAssertion($message ?: sprintf(
                'Failed asserting equality. Expected %s, got %s.',
                $this->export($expected),
                $this->export($actual)
            ));
        }
    }

    protected function assertNull(mixed $value, string $message = ''): void
    {
        self::$assertions++;
        if ($value !== null) {
            $this->failAssertion($message ?: 'Failed asserting that value is null.');
        }
    }

    protected function assertNotNull(mixed $value, string $message = ''): void
    {
        self::$assertions++;
        if ($value === null) {
            $this->failAssertion($message ?: 'Failed asserting that value is not null.');
        }
    }

    protected function assertCount(int $expected, array|\Countable $haystack, string $message = ''): void
    {
        self::$assertions++;
        $count = count($haystack);
        if ($count !== $expected) {
            $this->failAssertion($message ?: "Failed asserting count {$expected}, got {$count}.");
        }
    }

    protected function assertContains(mixed $needle, array $haystack, string $message = ''): void
    {
        self::$assertions++;
        if (! in_array($needle, $haystack, true)) {
            $this->failAssertion($message ?: 'Failed asserting array contains the value.');
        }
    }

    protected function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        self::$assertions++;
        if (! array_key_exists($key, $array)) {
            $this->failAssertion($message ?: "Failed asserting array has key {$key}.");
        }
    }

    protected function assertInstanceOf(string $class, mixed $object, string $message = ''): void
    {
        self::$assertions++;
        if (! $object instanceof $class) {
            $this->failAssertion($message ?: "Failed asserting instance of {$class}.");
        }
    }

    /**
     * Assert that the callback throws (optionally a specific exception class).
     */
    protected function assertThrows(callable $callback, ?string $expectedClass = null, string $message = ''): void
    {
        self::$assertions++;
        try {
            $callback();
        } catch (Throwable $e) {
            if ($expectedClass !== null && ! $e instanceof $expectedClass) {
                $this->failAssertion($message ?: sprintf(
                    'Expected %s, got %s: %s',
                    $expectedClass,
                    $e::class,
                    $e->getMessage()
                ));
            }
            return;
        }
        $this->failAssertion($message ?: 'Failed asserting that a throwable was thrown.');
    }

    protected function fail(string $message): void
    {
        $this->failAssertion($message);
    }

    private function failAssertion(string $message): void
    {
        throw new AssertionFailed($message);
    }

    private function export(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return 'array(' . count($value) . ')';
        }
        if (is_object($value)) {
            return $value::class;
        }
        return var_export($value, true);
    }
}
