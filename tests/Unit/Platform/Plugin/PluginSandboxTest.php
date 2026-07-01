<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Plugin;

use Nizam\Platform\Plugin\Event\PluginFailed;
use Nizam\Platform\Plugin\PluginId;
use Nizam\Platform\Plugin\RegisteredPlugin;
use Nizam\Platform\Plugin\Service\PluginSandbox;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for {@see PluginSandbox}: a throwing plugin call becomes a failure Result plus an event.
 */
#[CoversClass(PluginSandbox::class)]
#[CoversClass(PluginFailed::class)]
final class PluginSandboxTest extends TestCase
{
    private CollectingEventPublisher $publisher;
    private FixedTestClock $clock;
    private PluginSandbox $sandbox;
    private RegisteredPlugin $plugin;

    protected function setUp(): void
    {
        $this->publisher = new CollectingEventPublisher();
        $this->clock = new FixedTestClock();
        $this->sandbox = new PluginSandbox($this->publisher, $this->clock);
        $this->plugin = RegisteredPlugin::install(
            PluginId::generate(),
            PluginTestFactory::manifest('pkg.a', '1.0.0'),
            'array:pkg.a@1.0.0',
            null,
            $this->clock,
        );
    }

    #[Test]
    public function aSuccessfulCallReturnsAnOkResult(): void
    {
        $result = $this->sandbox->run($this->plugin, static fn (): string => 'done');

        self::assertTrue($result->isOk());
        self::assertSame('done', $result->value());
        self::assertSame([], $this->publisher->all());
    }

    #[Test]
    public function aThrowingCallBecomesAFailureResult(): void
    {
        $result = $this->sandbox->run($this->plugin, static function (): void {
            throw new RuntimeException('kaboom');
        });

        self::assertTrue($result->isErr());
        self::assertSame(PluginSandbox::CODE, $result->errorCode());
        self::assertStringContainsString('kaboom', (string) $result->errorMessage());
    }

    #[Test]
    public function aThrowingCallCarriesFaultContext(): void
    {
        $result = $this->sandbox->run($this->plugin, static function (): void {
            throw new RuntimeException('kaboom');
        });

        $context = $result->context();
        self::assertSame('pkg.a', $context['plugin']);
        self::assertSame(RuntimeException::class, $context['exception']);
    }

    #[Test]
    public function aThrowingCallPublishesAPluginFailedEvent(): void
    {
        $this->sandbox->run($this->plugin, static function (): void {
            throw new RuntimeException('kaboom');
        });

        $failures = $this->publisher->ofType(PluginFailed::class);
        self::assertCount(1, $failures);
        self::assertSame('pkg.a', $failures[0]->pluginName());
        self::assertStringContainsString('kaboom', $failures[0]->reason());
        self::assertStringContainsString(RuntimeException::class, $failures[0]->reason());
        self::assertSame($this->clock->now()->getTimestamp(), $failures[0]->occurredAt()->getTimestamp());
    }

    #[Test]
    public function itContainsErrorsAsWellAsExceptions(): void
    {
        $result = $this->sandbox->run($this->plugin, static function (): void {
            throw new \TypeError('type fault');
        });

        self::assertTrue($result->isErr());
        self::assertTrue($this->publisher->has(PluginFailed::class));
    }

    #[Test]
    public function theCoreNeverSeesTheThrowable(): void
    {
        // The whole point: no exception escapes run(); the call below must not throw.
        $result = $this->sandbox->run($this->plugin, static function (): void {
            throw new RuntimeException('isolated');
        });

        self::assertTrue($result->isErr());
    }
}
