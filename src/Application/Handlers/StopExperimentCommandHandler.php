<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\StopExperimentCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\DomainEventDispatcher;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Domain\Events\ExperimentStoppedEvent;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\InvalidStateTransition;
use Illuminate\Support\Carbon;

final readonly class StopExperimentCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(StopExperimentCommand $command): void
    {
        $record = $this->experimentRepository->getByKey($command->experimentKey);

        $currentStatus = ExperimentStatus::from($record->status);

        if (! $currentStatus->canTransitionTo(ExperimentStatus::completed)) {
            throw new InvalidStateTransition($currentStatus, ExperimentStatus::completed);
        }

        $beforeState = ['status' => $record->status];

        $this->experimentRepository->update($command->experimentKey, [
            'status' => ExperimentStatus::completed->value,
            'stopped_at' => Carbon::now(),
        ]);

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'stop',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: $beforeState,
            after: ['status' => ExperimentStatus::completed->value],
        );

        $this->eventDispatcher->dispatch(new ExperimentStoppedEvent(
            experimentKey: $command->experimentKey,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
