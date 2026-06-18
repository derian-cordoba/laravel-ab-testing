<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\ApproveExperimentCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Enums\ApprovalStatus;
use ABTests\Infrastructure\Database\Models\ExperimentApprovalModel;
use Illuminate\Support\Carbon;

final readonly class ApproveExperimentCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
    ) {}

    public function handle(ApproveExperimentCommand $command): void
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
                'status' => ApprovalStatus::approved->value,
                'reviewed_by' => $command->actorIdentifier,
                'reviewed_by_type' => $command->actorType,
                'notes' => $command->notes ?? $approval->notes,
                'reviewed_at' => Carbon::now(),
            ]);
        } else {
            ExperimentApprovalModel::query()->create([
                'experiment_key' => $command->experimentKey,
                'status' => ApprovalStatus::approved->value,
                'requested_by' => $command->actorIdentifier,
                'requested_by_type' => $command->actorType,
                'reviewed_by' => $command->actorIdentifier,
                'reviewed_by_type' => $command->actorType,
                'notes' => $command->notes,
                'reviewed_at' => Carbon::now(),
            ]);
        }

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'approve',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: ['approval_status' => ApprovalStatus::pending->value],
            after: ['approval_status' => ApprovalStatus::approved->value],
        );
    }
}
