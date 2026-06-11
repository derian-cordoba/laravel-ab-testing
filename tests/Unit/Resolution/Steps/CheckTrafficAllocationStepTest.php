<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Resolution\Steps;

use ABTests\Enums\ExperimentStatus;
use ABTests\Resolution\Steps\CheckTrafficAllocationStep;
use ABTests\Tests\Support\MakesPayload;
use ABTests\Values\ExperimentState;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckTrafficAllocationStepTest extends TestCase
{
    use MakesPayload;

    #[Test]
    public function returns_true_when_position_within_traffic_slice(): void
    {
        $state = new ExperimentState('exp', ExperimentStatus::running, 50);
        $payload = $this->makePayload(state: $state, bucketPosition: 0.3);

        self::assertTrue(new CheckTrafficAllocationStep()->handle($payload));
    }

    #[Test]
    public function returns_false_when_position_outside_traffic_slice(): void
    {
        $state = new ExperimentState('exp', ExperimentStatus::running, 50);
        $payload = $this->makePayload(state: $state, bucketPosition: 0.7);

        self::assertFalse(new CheckTrafficAllocationStep()->handle($payload));
    }

    #[Test]
    public function returns_false_at_exact_boundary(): void
    {
        $state = new ExperimentState('exp', ExperimentStatus::running, 50);
        $payload = $this->makePayload(state: $state, bucketPosition: 0.5);

        self::assertFalse(new CheckTrafficAllocationStep()->handle($payload));
    }

    #[Test]
    public function returns_true_for_100_percent_traffic(): void
    {
        $state = ExperimentState::alwaysRunning('exp');
        $payload = $this->makePayload(state: $state, bucketPosition: 0.9999);

        self::assertTrue(new CheckTrafficAllocationStep()->handle($payload));
    }
}
