<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Plugin;

use Nizam\Platform\Plugin\Exception\PluginDependencyException;
use Nizam\Platform\Plugin\PluginManifest;
use Nizam\Platform\Plugin\Service\PluginDependencyResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see PluginDependencyResolver}: topological order, cycles, missing/incompatible deps.
 */
#[CoversClass(PluginDependencyResolver::class)]
final class PluginDependencyResolverTest extends TestCase
{
    private PluginDependencyResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PluginDependencyResolver();
    }

    /**
     * The manifest names, in the resolved order.
     *
     * @param list<PluginManifest> $ordered
     *
     * @return list<string>
     */
    private static function names(array $ordered): array
    {
        return array_map(static fn (PluginManifest $m): string => $m->name(), $ordered);
    }

    #[Test]
    public function itOrdersDependenciesBeforeDependants(): void
    {
        // c depends on b, b depends on a. Input deliberately out of order.
        $a = PluginTestFactory::manifest('pkg.a', '1.0.0');
        $b = PluginTestFactory::manifest('pkg.b', '1.0.0', [PluginTestFactory::requires('pkg.a', '^1.0')]);
        $c = PluginTestFactory::manifest('pkg.c', '1.0.0', [PluginTestFactory::requires('pkg.b', '^1.0')]);

        $order = self::names($this->resolver->resolveOrder([$c, $b, $a]));

        self::assertLessThan(array_search('pkg.b', $order, true), array_search('pkg.a', $order, true));
        self::assertLessThan(array_search('pkg.c', $order, true), array_search('pkg.b', $order, true));
        self::assertSame(['pkg.a', 'pkg.b', 'pkg.c'], $order);
    }

    #[Test]
    public function itIsDeterministicAmongIndependentPlugins(): void
    {
        $a = PluginTestFactory::manifest('pkg.a', '1.0.0');
        $b = PluginTestFactory::manifest('pkg.b', '1.0.0');
        $c = PluginTestFactory::manifest('pkg.c', '1.0.0');

        self::assertSame(['pkg.a', 'pkg.b', 'pkg.c'], self::names($this->resolver->resolveOrder([$a, $b, $c])));
    }

    #[Test]
    public function itDetectsADirectCycle(): void
    {
        $a = PluginTestFactory::manifest('pkg.a', '1.0.0', [PluginTestFactory::requires('pkg.b')]);
        $b = PluginTestFactory::manifest('pkg.b', '1.0.0', [PluginTestFactory::requires('pkg.a')]);

        $this->expectException(PluginDependencyException::class);
        $this->expectExceptionMessage('cycle detected');

        $this->resolver->resolveOrder([$a, $b]);
    }

    #[Test]
    public function itDetectsATransitiveCycle(): void
    {
        $a = PluginTestFactory::manifest('pkg.a', '1.0.0', [PluginTestFactory::requires('pkg.b')]);
        $b = PluginTestFactory::manifest('pkg.b', '1.0.0', [PluginTestFactory::requires('pkg.c')]);
        $c = PluginTestFactory::manifest('pkg.c', '1.0.0', [PluginTestFactory::requires('pkg.a')]);

        $this->expectException(PluginDependencyException::class);

        $this->resolver->resolveOrder([$a, $b, $c]);
    }

    #[Test]
    public function itThrowsOnAMissingRequiredDependency(): void
    {
        $a = PluginTestFactory::manifest('pkg.a', '1.0.0', [PluginTestFactory::requires('pkg.absent')]);

        $this->expectException(PluginDependencyException::class);
        $this->expectExceptionMessage('not available');

        $this->resolver->resolveOrder([$a]);
    }

    #[Test]
    public function itThrowsOnAnIncompatibleRequiredDependency(): void
    {
        $a = PluginTestFactory::manifest('pkg.a', '1.0.0');
        $b = PluginTestFactory::manifest('pkg.b', '1.0.0', [PluginTestFactory::requires('pkg.a', '^2.0')]);

        $this->expectException(PluginDependencyException::class);
        $this->expectExceptionMessage('no available version satisfies');

        $this->resolver->resolveOrder([$b, $a]);
    }

    #[Test]
    public function itIgnoresAMissingOptionalDependency(): void
    {
        $a = PluginTestFactory::manifest('pkg.a', '1.0.0', [PluginTestFactory::optionally('pkg.absent')]);

        $order = self::names($this->resolver->resolveOrder([$a]));

        self::assertSame(['pkg.a'], $order);
    }

    #[Test]
    public function itIgnoresAnUnsatisfiableOptionalDependency(): void
    {
        $a = PluginTestFactory::manifest('pkg.a', '1.0.0');
        $b = PluginTestFactory::manifest('pkg.b', '1.0.0', [PluginTestFactory::optionally('pkg.a', '^2.0')]);

        $order = self::names($this->resolver->resolveOrder([$b, $a]));

        self::assertContains('pkg.a', $order);
        self::assertContains('pkg.b', $order);
    }

    #[Test]
    public function aPresentSatisfiableOptionalStillConstrainsOrder(): void
    {
        $a = PluginTestFactory::manifest('pkg.a', '1.0.0');
        $b = PluginTestFactory::manifest('pkg.b', '1.0.0', [PluginTestFactory::optionally('pkg.a', '^1.0')]);

        $order = self::names($this->resolver->resolveOrder([$b, $a]));

        self::assertLessThan(array_search('pkg.b', $order, true), array_search('pkg.a', $order, true));
    }

    #[Test]
    public function itRejectsDuplicateNames(): void
    {
        $a1 = PluginTestFactory::manifest('pkg.a', '1.0.0');
        $a2 = PluginTestFactory::manifest('pkg.a', '2.0.0');

        $this->expectException(PluginDependencyException::class);

        $this->resolver->resolveOrder([$a1, $a2]);
    }
}
