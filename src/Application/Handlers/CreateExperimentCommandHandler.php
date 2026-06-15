<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\CreateExperimentCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Enums\ExperimentStatus;

final readonly class CreateExperimentCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
    ) {
    }

    public function handle(CreateExperimentCommand $command): void
    {
        $this->experimentRepository->create([
            'key'                => $command->key,
            'name'               => $command->name,
            'layer'              => $command->layer,
            'status'             => ExperimentStatus::draft->value,
            'version'            => 1,
            'traffic_percentage' => $command->trafficPercentage,
            'is_killed'          => false,
        ]);

        $this->auditLogRepository->append(
            experimentKey: $command->key,
            action: 'create_experiment',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: [],
            after: [
                'name'               => $command->name,
                'layer'              => $command->layer,
                'status'             => ExperimentStatus::draft->value,
                'traffic_percentage' => $command->trafficPercentage,
            ],
        );
    }
}
