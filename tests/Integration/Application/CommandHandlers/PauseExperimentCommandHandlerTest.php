<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Application\CommandHandlers;

use ABTests\Application\Commands\PauseExperimentCommand;
use ABTests\Application\Handlers\PauseExperimentCommandHandler;
use ABTests\Domain\Events\ExperimentPausedEvent;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Exceptions\InvalidStateTransition;
use ABTests\Infrastructure\Database\DatabaseAuditLogRepository;
use ABTests\Infrastructure\Database\DatabaseExperimentRepository;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Tests\Integration\DatabaseTestCase;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;

final class PauseExperimentCommandHandlerTest extends DatabaseTestCase
{
    #[Test]
    public function pause_transitions_running_experiment_to_paused(): void
    {
        ExperimentModel::query()->create([
            'key' => 'my-exp',
            'status' => ExperimentStatus::running->value,
            'traffic_percentage' => 100,
            'is_killed' => false,
        ]);

        (new PauseExperimentCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new PauseExperimentCommand(
            experimentKey: 'my-exp',
            actorIdentifier: 'tester',
        ));

        $model = ExperimentModel::query()->firstWhere('key', 'my-exp');

        self::assertSame(ExperimentStatus::paused->value, $model->status);
    }

    #[Test]
    public function pause_throws_when_experiment_does_not_exist(): void
    {
        $this->expectException(ExperimentNotFound::class);

        (new PauseExperimentCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new PauseExperimentCommand(
            experimentKey: 'nonexistent',
            actorIdentifier: 'tester',
        ));
    }

    #[Test]
    public function pause_throws_when_experiment_is_not_running(): void
    {
        ExperimentModel::query()->create([
            'key' => 'my-exp',
            'status' => ExperimentStatus::draft->value,
            'traffic_percentage' => 0,
            'is_killed' => false,
        ]);

        $this->expectException(InvalidStateTransition::class);

        (new PauseExperimentCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new PauseExperimentCommand(
            experimentKey: 'my-exp',
            actorIdentifier: 'tester',
        ));
    }

    #[Test]
    public function dispatches_experiment_paused_event(): void
    {
        ExperimentModel::query()->create([
            'key' => 'my-exp',
            'status' => ExperimentStatus::running->value,
            'traffic_percentage' => 100,
            'is_killed' => false,
        ]);

        /** @var list<ExperimentPausedEvent> $fired */
        $fired = [];
        Container::getInstance()->make('events')->listen(
            ExperimentPausedEvent::class,
            static function (ExperimentPausedEvent $event) use (&$fired): void {
                $fired[] = $event;
            },
        );

        (new PauseExperimentCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new PauseExperimentCommand(
            experimentKey: 'my-exp',
            actorIdentifier: 'alice',
        ));

        self::assertCount(1, $fired);
        self::assertSame('my-exp', $fired[0]->experimentKey);
        self::assertSame('alice', $fired[0]->actorIdentifier);
    }
}
