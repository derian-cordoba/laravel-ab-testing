<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\ToggleKillSwitchCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Domain\Events\KillSwitchActivatedEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

final readonly class ToggleKillSwitchCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
    ) {}

    public function handle(ToggleKillSwitchCommand $command): void
    {
        $model = $this->experimentRepository->getByKey($command->experimentKey);

        $beforeState = ['is_killed' => $model->is_killed];

        $model->update([
            'is_killed' => $command->isKilled,
            'killed_at' => $command->isKilled ? Carbon::now() : null,
        ]);

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'kill',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: $beforeState,
            after: ['is_killed' => $command->isKilled],
        );

        Event::dispatch(new KillSwitchActivatedEvent(
            experimentKey: $command->experimentKey,
            flagKey: null,
            activated: $command->isKilled,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
