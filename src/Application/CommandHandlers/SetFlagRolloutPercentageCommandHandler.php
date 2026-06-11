<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\SetFlagRolloutPercentageCommand;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;

final readonly class SetFlagRolloutPercentageCommandHandler
{
    public function handle(SetFlagRolloutPercentageCommand $command): void
    {
        FeatureFlagStateModel::query()->updateOrCreate(
            ['key' => $command->flagKey],
            ['rollout_percentage' => $command->percentage],
        );
    }
}
