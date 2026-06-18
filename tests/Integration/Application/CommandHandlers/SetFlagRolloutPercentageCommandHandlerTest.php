<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Application\CommandHandlers;

use ABTests\Application\Commands\SetFlagRolloutPercentageCommand;
use ABTests\Application\Handlers\SetFlagRolloutPercentageCommandHandler;
use ABTests\Infrastructure\Database\DatabaseFeatureFlagRepository;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Tests\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SetFlagRolloutPercentageCommandHandlerTest extends DatabaseTestCase
{
    #[Test]
    public function creates_record_with_percentage_when_no_state_exists(): void
    {
        (new SetFlagRolloutPercentageCommandHandler(new DatabaseFeatureFlagRepository()))->handle(new SetFlagRolloutPercentageCommand(
            flagKey: 'my-flag',
            percentage: 40,
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'my-flag');

        self::assertNotNull($model);
        self::assertSame(40, $model->rollout_percentage);
    }

    #[Test]
    public function updates_percentage_on_existing_record(): void
    {
        FeatureFlagStateModel::query()->create([
            'key' => 'my-flag',
            'is_enabled' => true,
            'rollout_percentage' => 10,
        ]);

        (new SetFlagRolloutPercentageCommandHandler(new DatabaseFeatureFlagRepository()))->handle(new SetFlagRolloutPercentageCommand(
            flagKey: 'my-flag',
            percentage: 75,
            actorIdentifier: 'tester',
        ));

        self::assertSame(
            75,
            FeatureFlagStateModel::query()->firstWhere('key', 'my-flag')->rollout_percentage,
        );
    }

    #[Test]
    public function does_not_change_is_enabled_when_updating_percentage(): void
    {
        FeatureFlagStateModel::query()->create([
            'key' => 'my-flag',
            'is_enabled' => true,
            'rollout_percentage' => 100,
        ]);

        (new SetFlagRolloutPercentageCommandHandler(new DatabaseFeatureFlagRepository()))->handle(new SetFlagRolloutPercentageCommand(
            flagKey: 'my-flag',
            percentage: 50,
            actorIdentifier: 'tester',
        ));

        self::assertTrue(
            FeatureFlagStateModel::query()->firstWhere('key', 'my-flag')->is_enabled,
        );
    }

    #[Test]
    public function accepts_zero_percent_rollout(): void
    {
        (new SetFlagRolloutPercentageCommandHandler(new DatabaseFeatureFlagRepository()))->handle(new SetFlagRolloutPercentageCommand(
            flagKey: 'my-flag',
            percentage: 0,
            actorIdentifier: 'tester',
        ));

        self::assertSame(
            0,
            FeatureFlagStateModel::query()->firstWhere('key', 'my-flag')->rollout_percentage,
        );
    }

    #[Test]
    public function accepts_full_100_percent_rollout(): void
    {
        (new SetFlagRolloutPercentageCommandHandler(new DatabaseFeatureFlagRepository()))->handle(new SetFlagRolloutPercentageCommand(
            flagKey: 'my-flag',
            percentage: 100,
            actorIdentifier: 'tester',
        ));

        self::assertSame(
            100,
            FeatureFlagStateModel::query()->firstWhere('key', 'my-flag')->rollout_percentage,
        );
    }
}
