<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Application\CommandHandlers;

use ABTests\Application\Commands\StopExperimentCommand;
use ABTests\Application\Handlers\StopExperimentCommandHandler;
use ABTests\Domain\Events\ExperimentStoppedEvent;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Exceptions\InvalidStateTransition;
use ABTests\Infrastructure\Database\DatabaseAuditLogRepository;
use ABTests\Infrastructure\Database\DatabaseExperimentRepository;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Tests\Integration\DatabaseTestCase;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;

final class StopExperimentCommandHandlerTest extends DatabaseTestCase
{
    #[Test]
    public function stop_transitions_running_experiment_to_completed(): void
    {
        ExperimentModel::query()->create([
            'key' => 'my-exp',
            'status' => ExperimentStatus::running->value,
            'traffic_percentage' => 100,
            'is_killed' => false,
        ]);

        (new StopExperimentCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new StopExperimentCommand(
            experimentKey: 'my-exp',
            actorIdentifier: 'tester',
        ));

        $model = ExperimentModel::query()->firstWhere('key', 'my-exp');

        self::assertSame(ExperimentStatus::completed->value, $model->status);
        self::assertNotNull($model->stopped_at);
    }

    #[Test]
    public function stop_transitions_paused_experiment_to_completed(): void
    {
        ExperimentModel::query()->create([
            'key' => 'my-exp',
            'status' => ExperimentStatus::paused->value,
            'traffic_percentage' => 100,
            'is_killed' => false,
        ]);

        (new StopExperimentCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new StopExperimentCommand(
            experimentKey: 'my-exp',
            actorIdentifier: 'tester',
        ));

        $model = ExperimentModel::query()->firstWhere('key', 'my-exp');

        self::assertSame(ExperimentStatus::completed->value, $model->status);
    }

    #[Test]
    public function stop_throws_when_experiment_does_not_exist(): void
    {
        $this->expectException(ExperimentNotFound::class);

        (new StopExperimentCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new StopExperimentCommand(
            experimentKey: 'nonexistent',
            actorIdentifier: 'tester',
        ));
    }

    #[Test]
    public function stop_throws_when_experiment_is_in_draft(): void
    {
        ExperimentModel::query()->create([
            'key' => 'my-exp',
            'status' => ExperimentStatus::draft->value,
            'traffic_percentage' => 0,
            'is_killed' => false,
        ]);

        $this->expectException(InvalidStateTransition::class);

        (new StopExperimentCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new StopExperimentCommand(
            experimentKey: 'my-exp',
            actorIdentifier: 'tester',
        ));
    }

    #[Test]
    public function dispatches_experiment_stopped_event(): void
    {
        ExperimentModel::query()->create([
            'key' => 'my-exp',
            'status' => ExperimentStatus::running->value,
            'traffic_percentage' => 100,
            'is_killed' => false,
        ]);

        /** @var list<ExperimentStoppedEvent> $fired */
        $fired = [];
        Container::getInstance()->make('events')->listen(
            ExperimentStoppedEvent::class,
            static function (ExperimentStoppedEvent $event) use (&$fired): void {
                $fired[] = $event;
            },
        );

        (new StopExperimentCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new StopExperimentCommand(
            experimentKey: 'my-exp',
            actorIdentifier: 'alice',
        ));

        self::assertCount(1, $fired);
        self::assertSame('my-exp', $fired[0]->experimentKey);
        self::assertSame('alice', $fired[0]->actorIdentifier);
    }
}
