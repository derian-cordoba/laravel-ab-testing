<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\ResumeExperimentCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Domain\Events\ExperimentResumedEvent;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\InvalidStateTransition;
use Illuminate\Support\Facades\Event;

final readonly class ResumeExperimentCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
    ) {
    }

    public function handle(ResumeExperimentCommand $command): void
    {
        $model = $this->experimentRepository->getByKey($command->experimentKey);

        $currentStatus = ExperimentStatus::from($model->status);

        if (! $currentStatus->canTransitionTo(ExperimentStatus::running)) {
            throw new InvalidStateTransition($currentStatus, ExperimentStatus::running);
        }

        $beforeState = ['status' => $model->status];

        $model->update(['status' => ExperimentStatus::running->value]);

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'resume',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: $beforeState,
            after: ['status' => ExperimentStatus::running->value],
        );

        Event::dispatch(new ExperimentResumedEvent(
            experimentKey: $command->experimentKey,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
