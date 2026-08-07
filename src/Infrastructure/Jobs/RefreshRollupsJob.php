<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Jobs;

use ABTests\Application\Registry\AttributeReader;
use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Contracts\DomainEventDispatcher;
use ABTests\Definitions\MetricBinding;
use ABTests\Domain\Events\GuardrailBreachedEvent;
use ABTests\Domain\Guardrails\GuardrailBreach;
use ABTests\Domain\Guardrails\GuardrailEvaluationService;
use ABTests\Domain\Guardrails\RollupSummary;
use ABTests\Enums\ExperimentStatus;
use ABTests\Enums\MetricType;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\GuardrailBreachModel;
use ABTests\Infrastructure\Database\Models\RollupModel;
use ABTests\Infrastructure\Database\RollupAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Watermarked incremental rollup job. Processes events newer than the last
 * updated_through_at watermark for each active experiment, upserts rollup rows,
 * and checks guardrail thresholds after each cycle.
 *
 * Responsibilities are delegated to focused services:
 *   - RollupAggregator    — event chunking, delta accumulation, DB upsert
 *   - GuardrailEvaluationService — pure threshold computation
 *
 * count_of_units is computed via COUNT(DISTINCT unit_key) — a full recount
 * acceptable for v1 scales. See DASHBOARD.md §Open Questions for the v2 path.
 */
final class RefreshRollupsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ExperimentRegistry $registry, DomainEventDispatcher $eventDispatcher): void
    {
        $aggregator = new RollupAggregator();
        $evaluator = new GuardrailEvaluationService();
        $activeStatuses = [ExperimentStatus::running->value, ExperimentStatus::paused->value];

        ExperimentModel::query()
            ->whereIn('status', $activeStatuses)
            ->each(function (ExperimentModel $experiment) use ($registry, $eventDispatcher, $aggregator, $evaluator): void {
                $this->refreshExperiment($experiment, $registry, $eventDispatcher, $aggregator, $evaluator);
            });
    }

    /**
     * Refresh rollups for one specific experiment, regardless of lifecycle
     * status. This is used by the dashboard's manual "Refresh Data" control,
     * which should work for completed experiments too.
     */
    public function refreshExperimentByKey(string $experimentKey, ExperimentRegistry $registry, DomainEventDispatcher $eventDispatcher): bool
    {
        $experiment = ExperimentModel::query()->firstWhere('key', $experimentKey);

        if ($experiment === null) {
            return false;
        }

        $this->refreshExperiment($experiment, $registry, $eventDispatcher, new RollupAggregator(), new GuardrailEvaluationService());

        return true;
    }

    private function refreshExperiment(
        ExperimentModel $experiment,
        ExperimentRegistry $registry,
        DomainEventDispatcher $eventDispatcher,
        RollupAggregator $aggregator,
        GuardrailEvaluationService $evaluator,
    ): void {
        try {
            $definition = $registry->findByKey($experiment->key);
        } catch (Throwable) {
            // Experiment is in the DB but not yet in the registry (runtime-defined).
            // Skip until the registry supports runtime metrics (v2).
            return;
        }

        $metricKeys = array_values(array_unique(array_map(
            static fn (MetricBinding $binding): string => $binding->metric,
            $definition->metrics,
        )));

        if ($metricKeys === []) {
            return;
        }

        $ratioMetricKeys = $this->resolveRatioMetricKeys($definition->metrics, $registry, $experiment->key);

        $latestOccurredAt = $aggregator->aggregate($experiment->key, $metricKeys, $ratioMetricKeys);

        if ($latestOccurredAt !== null) {
            $this->handleGuardrails(
                experimentKey: $experiment->key,
                guardrails: $definition->guardrails(),
                controlVariantKey: $definition->allocation->control()->key(),
                evaluator: $evaluator,
                eventDispatcher: $eventDispatcher,
            );
        }
    }

    /**
     * Load rollup data from the DB, run the pure domain evaluation, then
     * persist new breaches and dispatch domain events.
     *
     * @param  list<MetricBinding>  $guardrails
     */
    private function handleGuardrails(
        string $experimentKey,
        array $guardrails,
        string $controlVariantKey,
        GuardrailEvaluationService $evaluator,
        DomainEventDispatcher $eventDispatcher,
    ): void {
        $rollupRows = RollupModel::query()
            ->where('experiment_key', $experimentKey)
            ->get();

        $summaries = $rollupRows->map(static fn (RollupModel $r): RollupSummary => new RollupSummary(
            variantKey: $r->variant_key,
            metricKey: $r->metric_key,
            conversions: (int) $r->conversions,
            countOfUnits: (int) $r->count_of_units,
        ))->all();

        $breaches = $evaluator->evaluate($guardrails, $summaries, $controlVariantKey);

        foreach ($breaches as $breach) {
            $this->persistBreach($experimentKey, $breach, $eventDispatcher);
        }
    }

    private function persistBreach(string $experimentKey, GuardrailBreach $breach, DomainEventDispatcher $eventDispatcher): void
    {
        $alreadyBreached = GuardrailBreachModel::query()
            ->where('experiment_key', $experimentKey)
            ->where('metric_key', $breach->metricKey)
            ->where('variant_key', $breach->variantKey)
            ->where('is_acknowledged', false)
            ->exists();

        if ($alreadyBreached) {
            return;
        }

        GuardrailBreachModel::query()->create([
            'experiment_key' => $experimentKey,
            'metric_key' => $breach->metricKey,
            'variant_key' => $breach->variantKey,
            'observed_value' => $breach->observedValue,
            'threshold_value' => $breach->thresholdValue,
            'breached_at' => Carbon::now(),
        ]);

        $eventDispatcher->dispatch(new GuardrailBreachedEvent(
            experimentKey: $experimentKey,
            metricKey: $breach->metricKey,
            variantKey: $breach->variantKey,
            observedValue: $breach->observedValue,
            thresholdValue: $breach->thresholdValue,
        ));
    }

    /**
     * Build a lookup of metric keys whose type is MetricType::Ratio.
     * Used to decide whether to populate the delta-method sufficient statistics
     * columns in the rollup table.
     *
     * @param  list<MetricBinding>  $metrics
     * @return array<string, true>
     */
    private function resolveRatioMetricKeys(array $metrics, ExperimentRegistry $registry, string $experimentKey): array
    {
        $experimentClass = $registry->findClassByKey($experimentKey);

        if ($experimentClass === null) {
            return [];
        }

        try {
            $reader = new AttributeReader();
            $metricTypes = $reader->readMetricTypes($experimentClass);

            $ratioKeys = [];
            foreach ($metricTypes as $key => $type) {
                if ($type === MetricType::ratio) {
                    $ratioKeys[$key] = true;
                }
            }

            return $ratioKeys;
        } catch (Throwable) {
            return [];
        }
    }
}
