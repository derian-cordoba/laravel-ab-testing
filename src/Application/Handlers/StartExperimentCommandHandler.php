<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\StartExperimentCommand;
use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\DomainEventDispatcher;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Domain\Events\ExperimentStartedEvent;
use ABTests\Enums\ApprovalStatus;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\ApprovalRequired;
use ABTests\Exceptions\InvalidStateTransition;
use ABTests\Infrastructure\Database\Models\ExperimentApprovalModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\VariantModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class StartExperimentCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
        private ExperimentRegistry $registry,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(StartExperimentCommand $command): void
    {
        $model = $this->experimentRepository->getByKey($command->experimentKey);

        $currentStatus = ExperimentStatus::from($model->status);

        if (! $currentStatus->canTransitionTo(ExperimentStatus::running)) {
            throw new InvalidStateTransition($currentStatus, ExperimentStatus::running);
        }

        // Approval gate: when governance.approval_required is true, the experiment
        // must have an approved review before it can start.
        if (config('ab-testing.governance.approval_required', false)) {
            $hasApproval = ExperimentApprovalModel::query()
                ->where('experiment_key', $command->experimentKey)
                ->where('status', ApprovalStatus::approved->value)
                ->exists();

            if (! $hasApproval) {
                throw new ApprovalRequired($command->experimentKey);
            }
        }

        // Power-analysis gate: warn or block depending on configuration.
        $powerAnalysisMode = config('ab-testing.governance.require_power_analysis', 'warn');

        if ($powerAnalysisMode !== 'off' && empty($model->target_sample_size)) {
            $message = "Experiment [{$command->experimentKey}] has no target_sample_size set. ".
                'Run a power analysis (ab:power-analysis or the dashboard) before starting.';

            if ($powerAnalysisMode === 'block') {
                throw new \DomainException($message);
            }

            Log::warning("[ABTesting] {$message}");
        }

        $beforeState = ['status' => $model->status, 'started_at' => $model->started_at];
        $trafficPercentage = $model->traffic_percentage > 0 ? $model->traffic_percentage : 100;

        $model->update([
            'status' => ExperimentStatus::running->value,
            'traffic_percentage' => $trafficPercentage,
            'started_at' => $model->started_at ?? Carbon::now(),
        ]);

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'start',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: $beforeState,
            after: [
                'status' => ExperimentStatus::running->value,
                'traffic_percentage' => $trafficPercentage,
            ],
        );

        $this->syncVariantSnapshot($model, $command->experimentKey);

        $this->eventDispatcher->dispatch(new ExperimentStartedEvent(
            experimentKey: $command->experimentKey,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            trafficPercentage: $trafficPercentage,
        ));
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
    private function syncVariantSnapshot(ExperimentModel $model, string $experimentKey): void
    {
        try {
            $definition = $this->registry->findByKey($experimentKey);
        } catch (Throwable) {
            // Runtime-defined experiment with no code definition — nothing to snapshot.
            return;
        }

        foreach ($definition->allocation->variants as $variant) {
            VariantModel::query()->updateOrCreate(
                ['experiment_id' => $model->id, 'key' => $variant->key()],
                ['weight' => $variant->weight(), 'is_control' => $variant->isControl()],
            );
        }
    }
}
