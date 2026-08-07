<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\StartExperimentCommand;
use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\DomainEventDispatcher;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Domain\Experiment\ExperimentAggregate;
use ABTests\Exceptions\ApprovalRequired;
use ABTests\Governance\Contracts\ApprovalPolicy;
use ABTests\Infrastructure\Database\Models\VariantModel;
use ABTests\Values\ExperimentRecord;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class StartExperimentCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
        private ExperimentRegistry $registry,
        private DomainEventDispatcher $eventDispatcher,
        private ApprovalPolicy $approvalPolicy,
    ) {}

    public function handle(StartExperimentCommand $command): void
    {
        $record = $this->experimentRepository->getByKey($command->experimentKey);

        // Approval gate: when governance.approval_required is true, the experiment
        // must have an approved review before it can start.
        if (config('ab-testing.governance.approval_required', false)) {
            if (! $this->approvalPolicy->hasApprovedReview($command->experimentKey)) {
                throw new ApprovalRequired($command->experimentKey);
            }
        }

        // Power-analysis gate: warn or block depending on configuration.
        $powerAnalysisMode = config('ab-testing.governance.require_power_analysis', 'warn');

        if ($powerAnalysisMode !== 'off' && empty($record->targetSampleSize)) {
            $message = "Experiment [{$command->experimentKey}] has no target_sample_size set. ".
                'Run a power analysis (ab:power-analysis or the dashboard) before starting.';

            if ($powerAnalysisMode === 'block') {
                throw new \DomainException($message);
            }

            Log::warning("[ABTesting] {$message}");
        }

        $aggregate = ExperimentAggregate::reconstitute($record);
        $aggregate->start($command->actorIdentifier, $command->actorType);

        $this->experimentRepository->update($command->experimentKey, $aggregate->pendingChanges());

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'start',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: $aggregate->beforeState(),
            after: $aggregate->pendingChanges(),
        );

        $this->syncVariantSnapshot($record, $command->experimentKey);

        $this->eventDispatcher->dispatchAll($aggregate->pullEvents());
    }

    /**
     * Snapshot the variant configuration from the code definition into the
     * database so the dashboard always has variant metadata, even after a
     * code-defined class is renamed or deleted.
     *
     * Each arm is upserted so repeated calls (e.g. on every deploy) are
     * idempotent — the first write creates the row, subsequent writes update
     * only the weight and is_control flag.
     */
    private function syncVariantSnapshot(ExperimentRecord $record, string $experimentKey): void
    {
        try {
            $definition = $this->registry->findByKey($experimentKey);
        } catch (Throwable) {
            // Runtime-defined experiment with no code definition — nothing to snapshot.
            return;
        }

        foreach ($definition->allocation->variants as $variant) {
            VariantModel::query()->updateOrCreate(
                ['experiment_id' => $record->id, 'key' => $variant->key()],
                ['weight' => $variant->weight(), 'is_control' => $variant->isControl()],
            );
        }
    }
}
