<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\CreateFeatureFlagCommand;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use Illuminate\Support\Carbon;

final readonly class CreateFeatureFlagCommandHandler
{
    public function handle(CreateFeatureFlagCommand $command): void
    {
        FeatureFlagStateModel::query()->create([
            'key'                => $command->key,
            'is_enabled'         => $command->isEnabled,
            'rollout_percentage' => $command->rolloutPercentage,
            'conditions'         => null,
        ]);

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type'       => $command->actorType,
            'action'           => 'create_feature_flag',
            'experiment_key'   => null,
            'before_state'     => null,
            'after_state'      => [
                'key'                => $command->key,
                'is_enabled'         => $command->isEnabled,
                'rollout_percentage' => $command->rolloutPercentage,
            ],
            'occurred_at'      => Carbon::now(),
        ]);
    }
}
