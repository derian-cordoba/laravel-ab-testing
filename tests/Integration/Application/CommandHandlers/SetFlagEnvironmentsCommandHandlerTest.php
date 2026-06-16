<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Application\CommandHandlers;

use ABTests\Application\Handlers\SetFlagEnvironmentsCommandHandler;
use ABTests\Application\Commands\SetFlagEnvironmentsCommand;
use ABTests\Domain\Events\FeatureFlagEnvironmentsUpdatedEvent;
use ABTests\Exceptions\FeatureFlagNotFound;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Tests\Integration\DatabaseTestCase;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use ABTests\Infrastructure\Database\DatabaseFeatureFlagRepository;
use ABTests\Infrastructure\Database\DatabaseAuditLogRepository;

final class SetFlagEnvironmentsCommandHandlerTest extends DatabaseTestCase
{
    #[Test]
    public function sets_allowed_environments_on_existing_flag(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'        => 'my-flag',
            'is_enabled' => true,
        ]);

        (new SetFlagEnvironmentsCommandHandler(new DatabaseFeatureFlagRepository(), new DatabaseAuditLogRepository()))->handle(new SetFlagEnvironmentsCommand(
            flagKey: 'my-flag',
            allowedEnvironments: ['production', 'staging'],
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'my-flag');

        self::assertSame(['production', 'staging'], $model->allowed_environments);
    }

    #[Test]
    public function clears_restriction_when_allowed_environments_is_null(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'                  => 'my-flag',
            'is_enabled'           => true,
            'allowed_environments' => ['production'],
        ]);

        (new SetFlagEnvironmentsCommandHandler(new DatabaseFeatureFlagRepository(), new DatabaseAuditLogRepository()))->handle(new SetFlagEnvironmentsCommand(
            flagKey: 'my-flag',
            allowedEnvironments: null,
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'my-flag');

        self::assertNull($model->allowed_environments);
    }

    #[Test]
    public function throws_when_flag_does_not_exist(): void
    {
        $this->expectException(FeatureFlagNotFound::class);

        (new SetFlagEnvironmentsCommandHandler(new DatabaseFeatureFlagRepository(), new DatabaseAuditLogRepository()))->handle(new SetFlagEnvironmentsCommand(
            flagKey: 'nonexistent',
            allowedEnvironments: ['production'],
            actorIdentifier: 'tester',
        ));
    }

    #[Test]
    public function does_not_change_other_flag_fields(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'                => 'my-flag',
            'is_enabled'         => true,
            'rollout_percentage' => 60,
        ]);

        (new SetFlagEnvironmentsCommandHandler(new DatabaseFeatureFlagRepository(), new DatabaseAuditLogRepository()))->handle(new SetFlagEnvironmentsCommand(
            flagKey: 'my-flag',
            allowedEnvironments: ['local'],
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'my-flag');

        self::assertTrue($model->is_enabled);
        self::assertSame(60, $model->rollout_percentage);
    }

    #[Test]
    public function dispatches_feature_flag_environments_updated_event(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'        => 'my-flag',
            'is_enabled' => false,
        ]);

        /** @var list<FeatureFlagEnvironmentsUpdatedEvent> $fired */
        $fired = [];
        Container::getInstance()->make('events')->listen(
            FeatureFlagEnvironmentsUpdatedEvent::class,
            static function (FeatureFlagEnvironmentsUpdatedEvent $event) use (&$fired): void {
                $fired[] = $event;
            },
        );

        (new SetFlagEnvironmentsCommandHandler(new DatabaseFeatureFlagRepository(), new DatabaseAuditLogRepository()))->handle(new SetFlagEnvironmentsCommand(
            flagKey: 'my-flag',
            allowedEnvironments: ['staging'],
            actorIdentifier: 'alice',
        ));

        self::assertCount(1, $fired);
        self::assertSame('my-flag', $fired[0]->flagKey);
        self::assertSame(['staging'], $fired[0]->allowedEnvironments);
        self::assertSame('alice', $fired[0]->actorIdentifier);
    }

    #[Test]
    public function event_carries_null_when_restriction_is_cleared(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'                  => 'my-flag',
            'is_enabled'           => true,
            'allowed_environments' => ['production'],
        ]);

        /** @var list<FeatureFlagEnvironmentsUpdatedEvent> $fired */
        $fired = [];
        Container::getInstance()->make('events')->listen(
            FeatureFlagEnvironmentsUpdatedEvent::class,
            static function (FeatureFlagEnvironmentsUpdatedEvent $event) use (&$fired): void {
                $fired[] = $event;
            },
        );

        (new SetFlagEnvironmentsCommandHandler(new DatabaseFeatureFlagRepository(), new DatabaseAuditLogRepository()))->handle(new SetFlagEnvironmentsCommand(
            flagKey: 'my-flag',
            allowedEnvironments: null,
            actorIdentifier: 'bob',
        ));

        self::assertCount(1, $fired);
        self::assertNull($fired[0]->allowedEnvironments);
    }
}
