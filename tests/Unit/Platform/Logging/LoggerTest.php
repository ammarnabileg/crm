<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Logging;

use DateTimeImmutable;
use Nizam\Kernel\Domain\Clock;
use Nizam\Platform\Logging\handlers\JsonLineHandler;
use Nizam\Platform\Logging\handlers\NullHandler;
use Nizam\Platform\Logging\Logger;
use Nizam\Platform\Logging\LogManager;
use Nizam\Platform\Support\Json;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;

#[CoversClass(Logger::class)]
#[CoversClass(JsonLineHandler::class)]
#[CoversClass(NullHandler::class)]
#[CoversClass(LogManager::class)]
final class LoggerTest extends TestCase
{
    private function fixedClock(): Clock
    {
        return new class () implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-07-01T12:00:00+00:00');
            }
        };
    }

    /**
     * Read every JSON record written to an in-memory stream.
     *
     * @param resource $stream
     *
     * @return array<int, array<string, mixed>>
     */
    private function readRecords($stream): array
    {
        rewind($stream);
        $records = [];

        foreach (explode("\n", trim((string) stream_get_contents($stream))) as $line) {
            if ($line !== '') {
                $records[] = Json::decode($line);
            }
        }

        return $records;
    }

    public function testWritesOneJsonLinePerRecord(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);

        $logger = new Logger('app', [new JsonLineHandler($stream)], $this->fixedClock());
        $logger->info('First');
        $logger->error('Second');

        $records = $this->readRecords($stream);

        self::assertCount(2, $records);
        self::assertSame('info', $records[0]['level']);
        self::assertSame('app', $records[0]['channel']);
        self::assertSame('First', $records[0]['message']);
        self::assertSame('2026-07-01T12:00:00.000+00:00', $records[0]['timestamp']);
        self::assertSame('error', $records[1]['level']);
    }

    public function testInterpolatesPlaceholders(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);

        $logger = new Logger('security', [new JsonLineHandler($stream)], $this->fixedClock());
        $logger->warning('User {userId} failed login from {ip}', ['userId' => 7, 'ip' => '10.0.0.1']);

        $records = $this->readRecords($stream);

        self::assertSame('User 7 failed login from 10.0.0.1', $records[0]['message']);
        self::assertSame(['userId' => 7, 'ip' => '10.0.0.1'], $records[0]['context']);
    }

    public function testInvalidLevelThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $logger = new Logger('app', [new NullHandler()], $this->fixedClock());
        $logger->log('not-a-level', 'message');
    }

    public function testLogManagerReturnsPsrLoggerPerChannel(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);

        $manager = new LogManager($this->fixedClock(), [new JsonLineHandler($stream)]);

        $app = $manager->channel('app');
        $security = $manager->channel('security');

        self::assertInstanceOf(LoggerInterface::class, $app);
        self::assertSame($app, $manager->channel('app'), 'Channel loggers must be cached.');
        self::assertNotSame($app, $security);

        $security->critical('breach');
        $records = $this->readRecords($stream);
        self::assertSame('security', $records[0]['channel']);
    }

    public function testLogManagerFallsBackToNullHandlerWhenNoHandlers(): void
    {
        $manager = new LogManager($this->fixedClock());

        // Must not throw even though no handlers were configured.
        $manager->channel('performance')->debug('timing');

        $this->expectNotToPerformAssertions();
    }
}
