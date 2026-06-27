<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\ValidationException;
use App\Core\Validator;
use Tests\TestCase;

/**
 * Validator message-builder robustness (production 500 regression). The error-message
 * map interpolates every parameter slot (min/max/between/same) regardless of which
 * rule failed, so a failing NO-parameter rule (url/email/integer/…) used to hit an
 * "Undefined array key" — which, on a server that turns warnings into exceptions,
 * turned an ordinary validation failure into a 500 (seen on POST /settings). A
 * failed validation must always surface as a ValidationException, never a crash.
 */
return new class extends TestCase {
    /** Reproduce a strict server: warnings become exceptions for the duration. */
    private function asStrict(callable $fn): \Throwable
    {
        set_error_handler(static function (int $level, string $message): bool {
            throw new \ErrorException($message);
        });
        try {
            $fn();
            throw new \RuntimeException('expected validation to fail');
        } catch (\Throwable $e) {
            return $e;
        } finally {
            restore_error_handler();
        }
    }

    public function test_failing_no_parameter_rule_does_not_crash_the_message_builder(): void
    {
        $thrown = $this->asStrict(static function (): void {
            Validator::make(['site' => 'not a url'], ['site' => 'url'])->validate();
        });
        // The friendly validation error, NOT an ErrorException from an undefined key.
        $this->assertInstanceOf(ValidationException::class, $thrown);
    }

    public function test_failing_integer_rule_does_not_crash(): void
    {
        $thrown = $this->asStrict(static function (): void {
            Validator::make(['n' => 'abc'], ['n' => 'integer'])->validate();
        });
        $this->assertInstanceOf(ValidationException::class, $thrown);
    }

    public function test_between_message_still_names_both_bounds(): void
    {
        try {
            Validator::make(['port' => 70000], ['port' => 'integer|between:1,65535'])->validate();
            $this->assertTrue(false, 'expected failure');
        } catch (ValidationException $e) {
            $message = $e->errors['port'][0] ?? '';
            $this->assertTrue(str_contains($message, '1'));
            $this->assertTrue(str_contains($message, '65535'));
        }
    }
};
