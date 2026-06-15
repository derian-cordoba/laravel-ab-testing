<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Infrastructure;

use ABTests\Enums\ExperimentStatus;
use ABTests\Infrastructure\Database\DatabaseExperimentStateRepository;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Tests\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\Test;

final class DatabaseExperimentStateRepositoryTest extends DatabaseTestCase
{
    private DatabaseExperimentStateRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new DatabaseExperimentStateRepository();
    }

    #[Test]
    public function returns_null_when_no_row_exists(): void
    {
        self::assertNull($this->repo->findState('unknown-experiment'));
    }

    #[Test]
    public function returns_state_for_running_experiment(): void
    {
        ExperimentModel::query()->create([
            'key' => 'checkout-button-color',
            'status' => 'running',
            'traffic_percentage' => 50,
            'is_killed' => false,
        ]);

        $state = $this->repo->findState('checkout-button-color');

        self::assertNotNull($state);
        self::assertSame('checkout-button-color', $state->experimentKey);
        self::assertSame(ExperimentStatus::running, $state->status);
        self::assertSame(50, $state->trafficPercentage);
        self::assertFalse($state->isKilled);
    }

    #[Test]
    public function returns_killed_state(): void
    {
        ExperimentModel::query()->create([
            'key' => 'exp',
            'status' => 'running',
            'traffic_percentage' => 100,
            'is_killed' => true,
        ]);

        $state = $this->repo->findState('exp');

        self::assertNotNull($state);
        self::assertTrue($state->isKilled);
        self::assertFalse($state->isActive());
    }

    #[Test]
    public function returns_paused_state(): void
    {
        ExperimentModel::query()->create([
            'key' => 'exp',
            'status' => 'paused',
            'traffic_percentage' => 30,
            'is_killed' => false,
        ]);

        $state = $this->repo->findState('exp');

        self::assertNotNull($state);
        self::assertSame(ExperimentStatus::paused, $state->status);
        self::assertFalse($state->isActive());
    }

    #[Test]
    public function active_running_experiment_is_active(): void
    {
        ExperimentModel::query()->create([
            'key' => 'exp',
            'status' => 'running',
            'traffic_percentage' => 100,
            'is_killed' => false,
        ]);

        $state = $this->repo->findState('exp');

        self::assertTrue($state?->isActive());
    }

    #[Test]
    public function maps_allowed_environments_from_database_row(): void
    {
        ExperimentModel::query()->create([
            'key'                  => 'exp',
            'status'               => 'running',
            'traffic_percentage'   => 100,
            'is_killed'            => false,
            'allowed_environments' => ['production', 'staging'],
        ]);

        $state = $this->repo->findState('exp');

        self::assertNotNull($state);
        self::assertSame(['production', 'staging'], $state->allowedEnvironments);
    }

    #[Test]
    public function allowed_environments_is_null_when_column_is_null(): void
    {
        ExperimentModel::query()->create([
            'key'                => 'exp',
            'status'             => 'running',
            'traffic_percentage' => 100,
            'is_killed'          => false,
        ]);

        $state = $this->repo->findState('exp');

        self::assertNotNull($state);
        self::assertNull($state->allowedEnvironments);
    }

    #[Test]
    public function different_experiments_returned_independently(): void
    {
        ExperimentModel::query()->create([
            'key' => 'exp-a',
            'status' => 'running',
            'traffic_percentage' => 100,
            'is_killed' => false,
        ]);

        ExperimentModel::query()->create([
            'key' => 'exp-b',
            'status' => 'paused',
            'traffic_percentage' => 50,
            'is_killed' => false,
        ]);

        self::assertSame(ExperimentStatus::running, $this->repo->findState('exp-a')?->status);
        self::assertSame(ExperimentStatus::paused, $this->repo->findState('exp-b')?->status);
    }
}
