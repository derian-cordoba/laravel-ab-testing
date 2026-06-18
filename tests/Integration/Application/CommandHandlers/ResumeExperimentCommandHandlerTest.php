<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Application\CommandHandlers;

use ABTests\Application\Commands\ResumeExperimentCommand;
use ABTests\Application\Handlers\ResumeExperimentCommandHandler;
use ABTests\Domain\Events\ExperimentResumedEvent;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Exceptions\InvalidStateTransition;
use ABTests\Infrastructure\Database\DatabaseAuditLogRepository;
use ABTests\Infrastructure\Database\DatabaseExperimentRepository;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Tests\Integration\DatabaseTestCase;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;

final class ResumeExperimentCommandHandlerTest extends DatabaseTestCase
{
    #[Test]
    public function resume_transitions_paused_experiment_to_running(): void
    {
        ExperimentModel::query()->create([
            'key' => 'my-exp',
            'status' => ExperimentStatus::paused->value,
            'traffic_percentage' => 100,
            'is_killed' => false,
        ]);

        (new ResumeExperimentCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new ResumeExperimentCommand(
            experimentKey: 'my-exp',
            actorIdentifier: 'tester',
        ));

        $model = ExperimentModel::query()->firstWhere('key', 'my-exp');

        self::assertSame(ExperimentStatus::running->value, $model->status);
    }

    #[Test]
    public function resume_throws_when_experiment_does_not_exist(): void
    {
        $this->expectException(ExperimentNotFound::class);

        (new ResumeExperimentCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new ResumeExperimentCommand(
            experimentKey: 'nonexistent',
            actorIdentifier: 'tester',
        ));
    }

    #[Test]
    public function resume_throws_when_experiment_cannot_transition_to_running(): void
    {
        ExperimentModel::query()->create([
            'key' => 'my-exp',
            'status' => ExperimentStatus::completed->value,
            'traffic_percentage' => 0,
            'is_killed' => false,
        ]);

        $this->expectException(InvalidStateTransition::class);

        (new ResumeExperimentCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new ResumeExperimentCommand(
            experimentKey: 'my-exp',
            actorIdentifier: 'tester',
        ));
    }

    #[Test]
    public function dispatches_experiment_resumed_event(): void
    {
        ExperimentModel::query()->create([
            'key' => 'my-exp',
            'status' => ExperimentStatus::paused->value,
            'traffic_percentage' => 100,
            'is_killed' => false,
        ]);

        /** @var list<ExperimentResumedEvent> $fired */
        $fired = [];
        Container::getInstance()->make('events')->listen(
            ExperimentResumedEvent::class,
            static function (ExperimentResumedEvent $event) use (&$fired): void {
                $fired[] = $event;
            },
        );

        (new ResumeExperimentCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new ResumeExperimentCommand(
            experimentKey: 'my-exp',
            actorIdentifier: 'alice',
        ));

        self::assertCount(1, $fired);
        self::assertSame('my-exp', $fired[0]->experimentKey);
        self::assertSame('alice', $fired[0]->actorIdentifier);
    }
}
