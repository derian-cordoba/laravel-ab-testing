<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Resolution\Steps;

use ABTests\Enums\ExperimentStatus;
use ABTests\Resolution\Steps\CheckExperimentActiveStep;
use ABTests\Tests\Support\MakesDefinition;
use ABTests\Tests\Support\MakesPayload;
use ABTests\Values\ExperimentState;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckExperimentActiveStepTest extends TestCase
{
    use MakesDefinition;
    use MakesPayload;

    #[Test]
    public function returns_true_when_experiment_is_running(): void
    {
        $state = ExperimentState::alwaysRunning('exp');
        $payload = $this->makePayload(state: $state);

        self::assertTrue(new CheckExperimentActiveStep()->handle($payload));
    }

    #[Test]
    public function returns_false_when_killed(): void
    {
        $state = new ExperimentState('exp', ExperimentStatus::running, 100, isKilled: true);
        $payload = $this->makePayload(state: $state);

        self::assertFalse(new CheckExperimentActiveStep()->handle($payload));
    }

    #[Test]
    public function returns_false_when_paused(): void
    {
        $state = new ExperimentState('exp', ExperimentStatus::paused, 100);
        $payload = $this->makePayload(state: $state);

        self::assertFalse(new CheckExperimentActiveStep()->handle($payload));
    }

    #[Test]
    public function returns_false_when_completed(): void
    {
        $state = new ExperimentState('exp', ExperimentStatus::completed, 100);
        $payload = $this->makePayload(state: $state);

        self::assertFalse(new CheckExperimentActiveStep()->handle($payload));
    }
}
