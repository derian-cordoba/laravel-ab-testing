<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Application\CommandHandlers;

use ABTests\Application\Commands\SetExperimentEnvironmentsCommand;
use ABTests\Application\Handlers\SetExperimentEnvironmentsCommandHandler;
use ABTests\Domain\Events\ExperimentEnvironmentsUpdatedEvent;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Infrastructure\Database\DatabaseAuditLogRepository;
use ABTests\Infrastructure\Database\DatabaseExperimentRepository;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Tests\Integration\DatabaseTestCase;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;

final class SetExperimentEnvironmentsCommandHandlerTest extends DatabaseTestCase
{
    #[Test]
    public function sets_allowed_environments_on_existing_experiment(): void
    {
        ExperimentModel::query()->create([
            'key' => 'my-exp',
            'status' => ExperimentStatus::running->value,
            'traffic_percentage' => 100,
            'is_killed' => false,
        ]);

        (new SetExperimentEnvironmentsCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new SetExperimentEnvironmentsCommand(
            experimentKey: 'my-exp',
            allowedEnvironments: ['production', 'staging'],
            actorIdentifier: 'tester',
        ));

        $model = ExperimentModel::query()->firstWhere('key', 'my-exp');

        self::assertSame(['production', 'staging'], $model->allowed_environments);
    }

    #[Test]
    public function clears_restriction_when_allowed_environments_is_null(): void
    {
        ExperimentModel::query()->create([
            'key' => 'my-exp',
            'status' => ExperimentStatus::running->value,
            'traffic_percentage' => 100,
            'is_killed' => false,
            'allowed_environments' => ['production'],
        ]);

        (new SetExperimentEnvironmentsCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new SetExperimentEnvironmentsCommand(
            experimentKey: 'my-exp',
            allowedEnvironments: null,
            actorIdentifier: 'tester',
        ));

        $model = ExperimentModel::query()->firstWhere('key', 'my-exp');

        self::assertNull($model->allowed_environments);
    }

    #[Test]
    public function throws_when_experiment_does_not_exist(): void
    {
        $this->expectException(ExperimentNotFound::class);

        (new SetExperimentEnvironmentsCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new SetExperimentEnvironmentsCommand(
            experimentKey: 'nonexistent',
            allowedEnvironments: ['production'],
            actorIdentifier: 'tester',
        ));
    }

    #[Test]
    public function does_not_change_other_experiment_fields(): void
    {
        ExperimentModel::query()->create([
            'key' => 'my-exp',
            'status' => ExperimentStatus::running->value,
            'traffic_percentage' => 75,
            'is_killed' => false,
        ]);

        (new SetExperimentEnvironmentsCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new SetExperimentEnvironmentsCommand(
            experimentKey: 'my-exp',
            allowedEnvironments: ['local'],
            actorIdentifier: 'tester',
        ));

        $model = ExperimentModel::query()->firstWhere('key', 'my-exp');

        self::assertSame(ExperimentStatus::running->value, $model->status);
        self::assertSame(75, $model->traffic_percentage);
        self::assertFalse($model->is_killed);
    }

    #[Test]
    public function dispatches_experiment_environments_updated_event(): void
    {
        ExperimentModel::query()->create([
            'key' => 'my-exp',
            'status' => ExperimentStatus::draft->value,
            'traffic_percentage' => 0,
            'is_killed' => false,
        ]);

        /** @var list<ExperimentEnvironmentsUpdatedEvent> $fired */
        $fired = [];
        Container::getInstance()->make('events')->listen(
            ExperimentEnvironmentsUpdatedEvent::class,
            static function (ExperimentEnvironmentsUpdatedEvent $event) use (&$fired): void {
                $fired[] = $event;
            },
        );

        (new SetExperimentEnvironmentsCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new SetExperimentEnvironmentsCommand(
            experimentKey: 'my-exp',
            allowedEnvironments: ['production'],
            actorIdentifier: 'alice',
        ));

        self::assertCount(1, $fired);
        self::assertSame('my-exp', $fired[0]->experimentKey);
        self::assertSame(['production'], $fired[0]->allowedEnvironments);
        self::assertSame('alice', $fired[0]->actorIdentifier);
    }

    #[Test]
    public function event_carries_null_when_restriction_is_cleared(): void
    {
        ExperimentModel::query()->create([
            'key' => 'my-exp',
            'status' => ExperimentStatus::running->value,
            'traffic_percentage' => 100,
            'is_killed' => false,
            'allowed_environments' => ['staging'],
        ]);

        /** @var list<ExperimentEnvironmentsUpdatedEvent> $fired */
        $fired = [];
        Container::getInstance()->make('events')->listen(
            ExperimentEnvironmentsUpdatedEvent::class,
            static function (ExperimentEnvironmentsUpdatedEvent $event) use (&$fired): void {
                $fired[] = $event;
            },
        );

        (new SetExperimentEnvironmentsCommandHandler(new DatabaseExperimentRepository(), new DatabaseAuditLogRepository()))->handle(new SetExperimentEnvironmentsCommand(
            experimentKey: 'my-exp',
            allowedEnvironments: null,
            actorIdentifier: 'bob',
        ));

        self::assertCount(1, $fired);
        self::assertNull($fired[0]->allowedEnvironments);
    }
}
