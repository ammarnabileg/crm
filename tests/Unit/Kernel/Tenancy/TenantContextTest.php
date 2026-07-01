<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Kernel\Tenancy;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Kernel\Domain\UserId;
use Nizam\Kernel\Tenancy\RequestContext;
use Nizam\Kernel\Tenancy\TenantContext;
use Nizam\Platform\Exception\PlatformException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TenantContext::class)]
#[CoversClass(RequestContext::class)]
final class TenantContextTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        $context = new TenantContext();

        self::assertFalse($context->hasTenant());
        self::assertNull($context->get());
    }

    public function testSetGetAndForget(): void
    {
        $context = new TenantContext();
        $tenant = TenantId::generate();

        $context->set($tenant);
        self::assertTrue($context->hasTenant());
        self::assertSame($tenant, $context->get());
        self::assertSame($tenant, $context->require());

        $context->forget();
        self::assertFalse($context->hasTenant());
    }

    public function testRequireThrowsWhenEmpty(): void
    {
        $this->expectException(PlatformException::class);

        (new TenantContext())->require();
    }

    public function testRunWithTenantRestoresPreviousTenant(): void
    {
        $context = new TenantContext();
        $outer = TenantId::generate();
        $inner = TenantId::generate();
        $context->set($outer);

        $seen = $context->runWithTenant($inner, function () use ($context, $inner): TenantId {
            self::assertSame($inner, $context->get());

            return $context->require();
        });

        self::assertSame($inner, $seen);
        self::assertSame($outer, $context->get(), 'Previous tenant must be restored.');
    }

    public function testRunWithTenantRestoresPreviousEvenOnException(): void
    {
        $context = new TenantContext();
        $outer = TenantId::generate();
        $inner = TenantId::generate();
        $context->set($outer);

        try {
            $context->runWithTenant($inner, static function (): void {
                throw new RuntimeException('boom');
            });
            self::fail('Expected exception was not thrown.');
        } catch (RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        self::assertSame($outer, $context->get());
    }

    public function testRunWithTenantRestoresNullWhenNoPreviousTenant(): void
    {
        $context = new TenantContext();

        $context->runWithTenant(TenantId::generate(), static fn (): null => null);

        self::assertFalse($context->hasTenant());
    }

    public function testRequestContextIsImmutableSnapshot(): void
    {
        $tenant = TenantId::generate();
        $user = UserId::generate();
        $base = new RequestContext('corr-123', $tenant);

        self::assertSame('corr-123', $base->correlationId());
        self::assertSame($tenant, $base->tenantId());
        self::assertNull($base->userId());
        self::assertFalse($base->isAuthenticated());

        $withUser = $base->withUser($user);
        self::assertSame($user, $withUser->userId());
        self::assertTrue($withUser->isAuthenticated());
        // Original is unchanged.
        self::assertNull($base->userId());
    }
}
