<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\SetFlagConditionsCommand;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;

final readonly class SetFlagConditionsCommandHandler
{
    public function handle(SetFlagConditionsCommand $command): void
    {
        FeatureFlagStateModel::query()->updateOrCreate(
            ['key' => $command->flagKey],
            [
                'conditions'       => $command->conditions ?: null,
                'conditions_logic' => $command->conditionsLogic,
            ],
        );
    }
}
