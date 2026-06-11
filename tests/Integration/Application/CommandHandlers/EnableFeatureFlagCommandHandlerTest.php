<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Application\CommandHandlers;

use ABTests\Application\CommandHandlers\EnableFeatureFlagCommandHandler;
use ABTests\Application\Commands\EnableFeatureFlagCommand;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Tests\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\Test;

final class EnableFeatureFlagCommandHandlerTest extends DatabaseTestCase
{
    #[Test]
    public function creates_record_and_enables_when_no_state_exists(): void
    {
        new EnableFeatureFlagCommandHandler()->handle(new EnableFeatureFlagCommand(
            flagKey: 'new-checkout',
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'new-checkout');

        self::assertNotNull($model);
        self::assertTrue($model->is_enabled);
    }

    #[Test]
    public function enables_an_existing_disabled_flag(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'        => 'new-checkout',
            'is_enabled' => false,
        ]);

        new EnableFeatureFlagCommandHandler()->handle(new EnableFeatureFlagCommand(
            flagKey: 'new-checkout',
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'new-checkout');

        self::assertTrue($model->is_enabled);
    }

    #[Test]
    public function enabling_an_already_enabled_flag_is_idempotent(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'        => 'new-checkout',
            'is_enabled' => true,
        ]);

        new EnableFeatureFlagCommandHandler()->handle(new EnableFeatureFlagCommand(
            flagKey: 'new-checkout',
            actorIdentifier: 'tester',
        ));

        self::assertSame(1, FeatureFlagStateModel::query()->where('key', 'new-checkout')->count());
        self::assertTrue(
            FeatureFlagStateModel::query()->firstWhere('key', 'new-checkout')->is_enabled
        );
    }

    #[Test]
    public function does_not_affect_other_flag_records(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'        => 'other-flag',
            'is_enabled' => false,
        ]);

        new EnableFeatureFlagCommandHandler()->handle(new EnableFeatureFlagCommand(
            flagKey: 'new-checkout',
            actorIdentifier: 'tester',
        ));

        self::assertFalse(
            FeatureFlagStateModel::query()->firstWhere('key', 'other-flag')->is_enabled
        );
    }
}
