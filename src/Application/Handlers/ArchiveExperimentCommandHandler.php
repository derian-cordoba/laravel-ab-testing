<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\ArchiveExperimentCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\InvalidStateTransition;

final readonly class ArchiveExperimentCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
    ) {}

    public function handle(ArchiveExperimentCommand $command): void
    {
        $model = $this->experimentRepository->getByKey($command->experimentKey);

        $currentStatus = ExperimentStatus::from($model->status);

        if (! $currentStatus->canTransitionTo(ExperimentStatus::archived)) {
            throw new InvalidStateTransition($currentStatus, ExperimentStatus::archived);
        }

        $beforeState = ['status' => $model->status];

        $model->update(['status' => ExperimentStatus::archived->value]);

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'archive',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: $beforeState,
            after: ['status' => ExperimentStatus::archived->value],
        );
    }
}
