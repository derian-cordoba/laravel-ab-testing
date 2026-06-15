<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\UpdateExperimentCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Enums\ExperimentStatus;
use DomainException;

final readonly class UpdateExperimentCommandHandler
{
    /** Statuses in which all metadata fields (including layer) may be changed. */
    private const array EDITABLE_STATUSES = [
        ExperimentStatus::draft->value,
        ExperimentStatus::scheduled->value,
    ];

    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
    ) {
    }

    public function handle(UpdateExperimentCommand $command): void
    {
        $model = $this->experimentRepository->getByKey($command->experimentKey);

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

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'update_experiment',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: $beforeState,
            after: $updates,
        );
    }
}
