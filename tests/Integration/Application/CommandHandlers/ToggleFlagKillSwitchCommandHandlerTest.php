<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Application\CommandHandlers;

use ABTests\Application\CommandHandlers\ToggleFlagKillSwitchCommandHandler;
use ABTests\Application\Commands\ToggleFlagKillSwitchCommand;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Tests\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ToggleFlagKillSwitchCommandHandlerTest extends DatabaseTestCase
{
    #[Test]
    public function activating_the_kill_switch_sets_killed_at_to_a_timestamp(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'        => 'my-flag',
            'is_enabled' => true,
            'killed_at'  => null,
        ]);

        new ToggleFlagKillSwitchCommandHandler()->handle(new ToggleFlagKillSwitchCommand(
            flagKey: 'my-flag',
            isKilled: true,
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'my-flag');

        self::assertNotNull($model->killed_at);
    }

    #[Test]
    public function deactivating_the_kill_switch_clears_killed_at(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'        => 'my-flag',
            'is_enabled' => true,
            'killed_at'  => \Carbon\Carbon::now(),
        ]);

        new ToggleFlagKillSwitchCommandHandler()->handle(new ToggleFlagKillSwitchCommand(
            flagKey: 'my-flag',
            isKilled: false,
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'my-flag');

        self::assertNull($model->killed_at);
    }

    #[Test]
    public function creates_record_when_no_state_exists_and_kill_switch_is_activated(): void
    {
        new ToggleFlagKillSwitchCommandHandler()->handle(new ToggleFlagKillSwitchCommand(
            flagKey: 'brand-new-flag',
            isKilled: true,
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'brand-new-flag');

        self::assertNotNull($model);
        self::assertNotNull($model->killed_at);
    }

    #[Test]
    public function does_not_change_is_enabled_when_toggling_kill_switch(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'        => 'my-flag',
            'is_enabled' => true,
            'killed_at'  => null,
        ]);

        new ToggleFlagKillSwitchCommandHandler()->handle(new ToggleFlagKillSwitchCommand(
            flagKey: 'my-flag',
            isKilled: true,
            actorIdentifier: 'tester',
        ));

        self::assertTrue(
            FeatureFlagStateModel::query()->firstWhere('key', 'my-flag')->is_enabled
        );
    }
}
