<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Application\CommandHandlers;

use ABTests\Application\CommandHandlers\SetFlagConditionsCommandHandler;
use ABTests\Application\Commands\SetFlagConditionsCommand;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Tests\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SetFlagConditionsCommandHandlerTest extends DatabaseTestCase
{
    #[Test]
    public function saves_conditions_to_existing_record(): void
    {
        FeatureFlagStateModel::query()->create(['key' => 'my-flag', 'is_enabled' => true]);

        $conditions = [
            ['attribute' => 'plan', 'operator' => 'equals', 'expected' => 'pro'],
        ];

        new SetFlagConditionsCommandHandler()->handle(new SetFlagConditionsCommand(
            flagKey: 'my-flag',
            conditions: $conditions,
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'my-flag');

        self::assertSame($conditions, $model->conditions);
    }

    #[Test]
    public function creates_record_with_conditions_when_no_state_exists(): void
    {
        $conditions = [
            ['attribute' => 'country', 'operator' => 'in', 'expected' => ['US', 'CA']],
        ];

        new SetFlagConditionsCommandHandler()->handle(new SetFlagConditionsCommand(
            flagKey: 'new-flag',
            conditions: $conditions,
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'new-flag');

        self::assertNotNull($model);
        self::assertSame($conditions, $model->conditions);
    }

    #[Test]
    public function empty_conditions_are_stored_as_null(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'        => 'my-flag',
            'is_enabled' => true,
            'conditions' => [['attribute' => 'plan', 'operator' => 'equals', 'expected' => 'pro']],
        ]);

        new SetFlagConditionsCommandHandler()->handle(new SetFlagConditionsCommand(
            flagKey: 'my-flag',
            conditions: [],
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'my-flag');

        self::assertNull($model->conditions);
    }

    #[Test]
    public function replaces_existing_conditions_with_new_ones(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'        => 'my-flag',
            'is_enabled' => true,
            'conditions' => [['attribute' => 'plan', 'operator' => 'equals', 'expected' => 'free']],
        ]);

        $newConditions = [
            ['attribute' => 'plan', 'operator' => 'equals', 'expected' => 'pro'],
            ['attribute' => 'country', 'operator' => 'equals', 'expected' => 'US'],
        ];

        new SetFlagConditionsCommandHandler()->handle(new SetFlagConditionsCommand(
            flagKey: 'my-flag',
            conditions: $newConditions,
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'my-flag');

        self::assertSame($newConditions, $model->conditions);
    }

    #[Test]
    public function does_not_change_is_enabled_when_updating_conditions(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'        => 'my-flag',
            'is_enabled' => true,
        ]);

        new SetFlagConditionsCommandHandler()->handle(new SetFlagConditionsCommand(
            flagKey: 'my-flag',
            conditions: [['attribute' => 'plan', 'operator' => 'equals', 'expected' => 'pro']],
            actorIdentifier: 'tester',
        ));

        self::assertTrue(
            FeatureFlagStateModel::query()->firstWhere('key', 'my-flag')->is_enabled
        );
    }
}
