<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Application\CommandHandlers;

use ABTests\Application\CommandHandlers\StartExperimentCommandHandler;
use ABTests\Application\Commands\StartExperimentCommand;
use ABTests\Enums\ExperimentStatus;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Registry\ExperimentRegistry;
use ABTests\Tests\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\Test;

final class StartExperimentCommandHandlerTest extends DatabaseTestCase
{
    #[Test]
    public function start_sets_traffic_to_full_when_experiment_is_at_zero_percent(): void
    {
        ExperimentModel::query()->create([
            'key' => 'checkout-button-color',
            'status' => ExperimentStatus::draft->value,
            'traffic_percentage' => 0,
            'is_killed' => false,
        ]);

        new StartExperimentCommandHandler(new ExperimentRegistry())->handle(new StartExperimentCommand(
            experimentKey: 'checkout-button-color',
            actorIdentifier: 'tester',
        ));

        $model = ExperimentModel::query()->firstWhere('key', 'checkout-button-color');

        self::assertNotNull($model);
        self::assertSame(ExperimentStatus::running->value, $model->status);
        self::assertSame(100, $model->traffic_percentage);
        self::assertNotNull($model->started_at);
    }

    #[Test]
    public function start_preserves_existing_non_zero_traffic_percentage(): void
    {
        ExperimentModel::query()->create([
            'key' => 'checkout-button-color',
            'status' => ExperimentStatus::scheduled->value,
            'traffic_percentage' => 25,
            'is_killed' => false,
        ]);

        new StartExperimentCommandHandler(new ExperimentRegistry())->handle(new StartExperimentCommand(
            experimentKey: 'checkout-button-color',
            actorIdentifier: 'tester',
        ));

        $model = ExperimentModel::query()->firstWhere('key', 'checkout-button-color');

        self::assertNotNull($model);
        self::assertSame(ExperimentStatus::running->value, $model->status);
        self::assertSame(25, $model->traffic_percentage);
    }
}
