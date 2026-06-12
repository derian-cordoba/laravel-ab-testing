<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\UpdateExperimentCommand;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use DomainException;
use Illuminate\Support\Carbon;

final readonly class UpdateExperimentCommandHandler
{
    /** Statuses in which all metadata fields (including layer) may be changed. */
    private const array EDITABLE_STATUSES = [
        ExperimentStatus::draft->value,
        ExperimentStatus::scheduled->value,
    ];

    public function handle(UpdateExperimentCommand $command): void
    {
        $model = ExperimentModel::query()->firstWhere('key', $command->experimentKey);

        if ($model === null) {
            throw new ExperimentNotFound($command->experimentKey);
        }

        // Layer is a structural assignment field — once the experiment is live
        // the bucketing namespace must not change, as it would break mutual exclusion.
        $layerLocked = ! in_array($model->status, self::EDITABLE_STATUSES, strict: true);

        if ($layerLocked && $command->layer !== $model->layer) {
            throw new DomainException(
                "The layer field cannot be changed once an experiment leaves draft/scheduled status. Current status: [$model->status].",
            );
        }

        $beforeState = [
            'name'               => $model->name,
            'layer'              => $model->layer,
            'target_sample_size' => $model->target_sample_size,
        ];

        $updates = ['name' => $command->name, 'target_sample_size' => $command->targetSampleSize];

        if (! $layerLocked) {
            $updates['layer'] = $command->layer;
        }

        $model->update($updates);

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type'       => $command->actorType,
            'action'           => 'update_experiment',
            'experiment_key'   => $command->experimentKey,
            'before_state'     => $beforeState,
            'after_state'      => $updates,
            'occurred_at'      => Carbon::now(),
        ]);
    }
}
