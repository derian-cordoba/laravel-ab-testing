<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Values;

use ABTests\Enums\ExperimentStatus;
use ABTests\Values\ExperimentState;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExperimentStateTest extends TestCase
{
    #[Test]
    public function always_running_factory_creates_active_state(): void
    {
        $state = ExperimentState::alwaysRunning('my-exp');

        self::assertSame('my-exp', $state->experimentKey);
        self::assertSame(ExperimentStatus::running, $state->status);
        self::assertSame(100, $state->trafficPercentage);
        self::assertFalse($state->isKilled);
        self::assertTrue($state->isActive());
    }

    #[Test]
    public function is_active_false_when_killed(): void
    {
        $state = new ExperimentState(
            experimentKey: 'exp',
            status: ExperimentStatus::running,
            trafficPercentage: 100,
            isKilled: true,
        );

        self::assertFalse($state->isActive());
    }

    #[Test]
    public function is_active_false_when_paused(): void
    {
        $state = new ExperimentState(
            experimentKey: 'exp',
            status: ExperimentStatus::paused,
            trafficPercentage: 100,
        );

        self::assertFalse($state->isActive());
    }

    #[Test]
    public function is_active_true_only_when_running_and_not_killed(): void
    {
        $state = new ExperimentState(
            experimentKey: 'exp',
            status: ExperimentStatus::running,
            trafficPercentage: 50,
        );

        self::assertTrue($state->isActive());
    }
}
