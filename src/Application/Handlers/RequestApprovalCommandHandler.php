<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\RequestApprovalCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Enums\ApprovalStatus;
use ABTests\Infrastructure\Database\Models\ExperimentApprovalModel;

final readonly class RequestApprovalCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
    ) {
    }

    public function handle(RequestApprovalCommand $command): ExperimentApprovalModel
    {
        $this->experimentRepository->getByKey($command->experimentKey);

        /** @var ExperimentApprovalModel $approval */
        $approval = ExperimentApprovalModel::query()->create([
            'experiment_key'    => $command->experimentKey,
            'status'            => ApprovalStatus::pending->value,
            'requested_by'      => $command->actorIdentifier,
            'requested_by_type' => $command->actorType,
            'notes'             => $command->notes,
        ]);

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'request_approval',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: [],
            after: ['approval_status' => ApprovalStatus::pending->value],
        );

        return $approval;
    }
}
