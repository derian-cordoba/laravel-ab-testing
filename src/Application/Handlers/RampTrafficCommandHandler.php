<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\RampTrafficCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\ExperimentRepository;

final readonly class RampTrafficCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
    ) {}

    public function handle(RampTrafficCommand $command): void
    {
        $record = $this->experimentRepository->getByKey($command->experimentKey);

        $percentage = max(0, min(100, $command->trafficPercentage));
        $beforeState = ['traffic_percentage' => $record->trafficPercentage];

        $this->experimentRepository->update($command->experimentKey, ['traffic_percentage' => $percentage]);

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'ramp_traffic',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: $beforeState,
            after: ['traffic_percentage' => $percentage],
        );
    }
}
