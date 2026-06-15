<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Resolution\Steps;

use ABTests\Enums\ExperimentStatus;
use ABTests\Resolution\Steps\CheckEnvironmentStep;
use ABTests\Tests\Support\MakesDefinition;
use ABTests\Tests\Support\MakesPayload;
use ABTests\Values\ExperimentState;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckEnvironmentStepTest extends TestCase
{
    use MakesDefinition;
    use MakesPayload;

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Container::setInstance();
        parent::tearDown();
    }

    // ── null list = unrestricted ──────────────────────────────────────────────

    #[Test]
    public function returns_true_when_allowed_environments_is_null(): void
    {
        // No app() call is made when the list is null — always pass.
        $state   = new ExperimentState('exp', ExperimentStatus::running, 100, allowedEnvironments: null);
        $payload = $this->makePayload(state: $state);

        self::assertTrue(new CheckEnvironmentStep()->handle($payload));
    }

    // ── empty list = no environment ───────────────────────────────────────────

    #[Test]
    public function returns_false_when_allowed_environments_is_empty(): void
    {
        $state   = new ExperimentState('exp', ExperimentStatus::running, 100, allowedEnvironments: []);
        $payload = $this->makePayload(state: $state);

        self::assertFalse(new CheckEnvironmentStep()->handle($payload));
    }

    // ── matching environment ──────────────────────────────────────────────────

    #[Test]
    public function returns_true_when_current_environment_is_in_allowed_list(): void
    {
        $this->bindEnvironment('production');

        $state   = new ExperimentState('exp', ExperimentStatus::running, 100, allowedEnvironments: ['production']);
        $payload = $this->makePayload(state: $state);

        self::assertTrue(new CheckEnvironmentStep()->handle($payload));
    }

    #[Test]
    public function returns_true_when_current_environment_matches_one_of_several(): void
    {
        $this->bindEnvironment('staging');

        $state   = new ExperimentState('exp', ExperimentStatus::running, 100, allowedEnvironments: ['local', 'staging', 'production']);
        $payload = $this->makePayload(state: $state);

        self::assertTrue(new CheckEnvironmentStep()->handle($payload));
    }

    // ── non-matching environment ──────────────────────────────────────────────

    #[Test]
    public function returns_false_when_current_environment_is_not_in_allowed_list(): void
    {
        $this->bindEnvironment('local');

        $state   = new ExperimentState('exp', ExperimentStatus::running, 100, allowedEnvironments: ['production', 'staging']);
        $payload = $this->makePayload(state: $state);

        self::assertFalse(new CheckEnvironmentStep()->handle($payload));
    }

    #[Test]
    public function returns_false_when_allowed_is_production_only_and_env_is_local(): void
    {
        $this->bindEnvironment('local');

        $state   = new ExperimentState('exp', ExperimentStatus::running, 100, allowedEnvironments: ['production']);
        $payload = $this->makePayload(state: $state);

        self::assertFalse(new CheckEnvironmentStep()->handle($payload));
    }

    // ── unknown environment string ────────────────────────────────────────────

    #[Test]
    public function returns_false_when_app_environment_is_not_a_valid_enum_value(): void
    {
        $this->bindEnvironment('some-custom-env');

        $state   = new ExperimentState('exp', ExperimentStatus::running, 100, allowedEnvironments: ['production']);
        $payload = $this->makePayload(state: $state);

        self::assertFalse(new CheckEnvironmentStep()->handle($payload));
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Bind a thin container that returns the given environment string from
     * app()->environment(), used by CheckEnvironmentStep.
     */
    private function bindEnvironment(string $env): void
    {
        $container = new class ($env) extends Container {
            public function __construct(private readonly string $env)
            {
                //
            }

            public function environment(): string
            {
                return $this->env;
            }
        };

        Container::setInstance($container);
    }
}
