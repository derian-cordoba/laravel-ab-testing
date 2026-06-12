<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\CreateExperimentCommand;
use ABTests\Enums\ExperimentStatus;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Support\Carbon;

final readonly class CreateExperimentCommandHandler
{
    public function handle(CreateExperimentCommand $command): void
    {
        ExperimentModel::query()->create([
            'key'                => $command->key,
            'name'               => $command->name,
            'layer'              => $command->layer,
            'status'             => ExperimentStatus::draft->value,
            'version'            => 1,
            'traffic_percentage' => $command->trafficPercentage,
            'is_killed'          => false,
        ]);

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type'       => $command->actorType,
            'action'           => 'create_experiment',
            'experiment_key'   => $command->key,
            'before_state'     => null,
            'after_state'      => [
                'name'               => $command->name,
                'layer'              => $command->layer,
                'status'             => ExperimentStatus::draft->value,
                'traffic_percentage' => $command->trafficPercentage,
            ],
            'occurred_at'      => Carbon::now(),
        ]);
    }
}
