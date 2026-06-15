<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\RejectExperimentCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Enums\ApprovalStatus;
use ABTests\Infrastructure\Database\Models\ExperimentApprovalModel;
use Illuminate\Support\Carbon;

final readonly class RejectExperimentCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
    ) {
    }

    public function handle(RejectExperimentCommand $command): void
    {
        $this->experimentRepository->getByKey($command->experimentKey);

        /** @var ExperimentApprovalModel|null $approval */
        $approval = ExperimentApprovalModel::query()
            ->where('experiment_key', $command->experimentKey)
            ->where('status', ApprovalStatus::pending->value)
            ->latest()
            ->first();

        if ($approval !== null) {
            $approval->update([
                'status'           => ApprovalStatus::rejected->value,
                'reviewed_by'      => $command->actorIdentifier,
                'reviewed_by_type' => $command->actorType,
                'notes'            => $command->notes ?? $approval->notes,
                'reviewed_at'      => Carbon::now(),
            ]);
        }

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'reject',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: ['approval_status' => ApprovalStatus::pending->value],
            after: ['approval_status' => ApprovalStatus::rejected->value],
        );
    }
}
