<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Infrastructure;

use ABTests\Enums\ExperimentStatus;
use ABTests\Infrastructure\AlwaysRunningExperimentStateRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AlwaysRunningExperimentStateRepositoryTest extends TestCase
{
    #[Test]
    public function always_returns_running_state_for_any_key(): void
    {
        $repo  = new AlwaysRunningExperimentStateRepository();
        $state = $repo->findState('any-experiment');

        self::assertSame('any-experiment', $state->experimentKey);
        self::assertSame(ExperimentStatus::running, $state->status);
        self::assertSame(100, $state->trafficPercentage);
        self::assertFalse($state->isKilled);
        self::assertTrue($state->isActive());
    }

    #[Test]
    public function returns_different_instances_but_same_values_for_different_keys(): void
    {
        $repo = new AlwaysRunningExperimentStateRepository();

        $stateA = $repo->findState('exp-a');
        $stateB = $repo->findState('exp-b');

        self::assertSame('exp-a', $stateA->experimentKey);
        self::assertSame('exp-b', $stateB->experimentKey);
    }
}
