<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Runtime\Execution;

use Nizam\Runtime\Execution\Application\TimeoutManager;
use Nizam\Runtime\Execution\Domain\Exception\ExecutionTimedOut;
use Nizam\Runtime\Execution\Domain\ValueObject\TimeoutPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the {@see TimeoutManager} enforces wall-clock and per-step budgets against a fake, advanceable
 * {@see \Nizam\Kernel\Domain\Clock}, raising {@see ExecutionTimedOut} once a cap is exceeded.
 */
#[CoversClass(TimeoutManager::class)]
final class TimeoutManagerTest extends TestCase
{
    public function testMeasuresElapsedTimeFromTheAnchorAsTheClockAdvances(): void
    {
        $clock = new MutableTestClock();
        $manager = TimeoutManager::start(new TimeoutPolicy(1_000, 500), $clock);

        self::assertSame(0, $manager->elapsedMs());

        $clock->advanceMs(250);
        self::assertSame(250, $manager->elapsedMs());
    }

    public function testWallClockWithinBudgetDoesNotThrow(): void
    {
        $clock = new MutableTestClock();
        $manager = TimeoutManager::start(new TimeoutPolicy(1_000, 500), $clock);

        $clock->advanceMs(1_000); // exactly at the cap is not "exceeded".
        self::assertFalse($manager->wallClockExceeded());
        $manager->assertWithinWallClock();
        $this->addToAssertionCount(1);
    }

    public function testExceedingTheWallClockBudgetThrows(): void
    {
        $clock = new MutableTestClock();
        $manager = TimeoutManager::start(new TimeoutPolicy(1_000, 500), $clock);

        $clock->advanceMs(1_001);

        self::assertTrue($manager->wallClockExceeded());
        $this->expectException(ExecutionTimedOut::class);
        $this->expectExceptionMessageMatches('/wall-clock/');
        $manager->assertWithinWallClock();
    }

    public function testWallClockErrorCarriesTheTimedOutCode(): void
    {
        $clock = new MutableTestClock();
        $manager = TimeoutManager::start(new TimeoutPolicy(100, 100), $clock);
        $clock->advanceMs(500);

        try {
            $manager->assertWithinWallClock();
            self::fail('Expected ExecutionTimedOut.');
        } catch (ExecutionTimedOut $e) {
            self::assertSame(ExecutionTimedOut::CODE, $e->errorCode());
        }
    }

    public function testStepWithinBudgetDoesNotThrow(): void
    {
        $clock = new MutableTestClock();
        $manager = TimeoutManager::start(new TimeoutPolicy(10_000, 500), $clock);

        $stepStart = $clock->now();
        $clock->advanceMs(500); // exactly at the per-step cap.

        self::assertFalse($manager->stepExceeded($stepStart));
        $manager->assertStepWithin('step-1', $stepStart);
        $this->addToAssertionCount(1);
    }

    public function testExceedingThePerStepBudgetThrows(): void
    {
        $clock = new MutableTestClock();
        $manager = TimeoutManager::start(new TimeoutPolicy(10_000, 500), $clock);

        $stepStart = $clock->now();
        $clock->advanceMs(750);

        self::assertTrue($manager->stepExceeded($stepStart));
        $this->expectException(ExecutionTimedOut::class);
        $this->expectExceptionMessageMatches('/step-1/');
        $manager->assertStepWithin('step-1', $stepStart);
    }

    public function testTheAnchorIsCapturedOnceAtStart(): void
    {
        $clock = new MutableTestClock();
        $anchor = $clock->now();
        $manager = TimeoutManager::start(new TimeoutPolicy(1_000, 500), $clock);

        $clock->advanceMs(300);

        self::assertEquals($anchor, $manager->startedAt());
    }
}
