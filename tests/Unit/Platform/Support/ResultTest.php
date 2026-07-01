<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Support;

use Nizam\Platform\Exception\PlatformException;
use Nizam\Platform\Support\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Result::class)]
final class ResultTest extends TestCase
{
    public function testOkCarriesValueAndReportsSuccess(): void
    {
        $result = Result::ok(42);

        self::assertTrue($result->isOk());
        self::assertFalse($result->isErr());
        self::assertSame(42, $result->value());
        self::assertNull($result->errorCode());
        self::assertNull($result->errorMessage());
    }

    public function testErrCarriesCodeAndMessage(): void
    {
        $result = Result::err('DOMAIN.RULE', 'Not allowed', ['field' => 'name']);

        self::assertTrue($result->isErr());
        self::assertSame('DOMAIN.RULE', $result->errorCode());
        self::assertSame('Not allowed', $result->errorMessage());
        self::assertSame(['field' => 'name'], $result->context());
    }

    public function testErrDefaultsMessageToCode(): void
    {
        self::assertSame('CODE_ONLY', Result::err('CODE_ONLY')->errorMessage());
    }

    public function testValueOnErrorThrows(): void
    {
        $this->expectException(PlatformException::class);

        Result::err('X', 'boom')->value();
    }

    public function testMapTransformsSuccessOnly(): void
    {
        self::assertSame(4, Result::ok(2)->map(static fn (int $n): int => $n * 2)->value());

        $err = Result::err('E');
        self::assertSame($err, $err->map(static fn (mixed $n): mixed => $n));
    }

    public function testMapErrTransformsErrorOnly(): void
    {
        $mapped = Result::err('A', 'a')->mapErr(
            static fn (string $code, string $msg, array $ctx): Result => Result::err('B', $msg),
        );

        self::assertSame('B', $mapped->errorCode());

        $ok = Result::ok(1);
        self::assertSame($ok, $ok->mapErr(static fn (string $c, string $m, array $x): Result => Result::err('Z')));
    }

    public function testUnwrapOrReturnsDefaultOnError(): void
    {
        self::assertSame(9, Result::ok(9)->unwrapOr(0));
        self::assertSame(0, Result::err('E')->unwrapOr(0));
    }
}
