<?php

declare(strict_types=1);

namespace ABTests\Application;

use ABTests\Application\Data\ExperimentResultsData;
use ABTests\Application\Data\VariantResultData;
use ABTests\Definitions\ExperimentDefinition;
use ABTests\Definitions\MetricBinding;
use ABTests\Enums\MetricRole;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\GuardrailBreachModel;
use ABTests\Infrastructure\Database\Models\RollupModel;
use ABTests\Registry\ExperimentRegistry;
use ABTests\Statistics\AnalysisService;
use ABTests\Values\Allocation;
use ABTests\Values\AnalysisConfiguration;
use ABTests\Values\GenericVariant;
use ABTests\Values\MetricSummary;
use ABTests\Values\SampleRatioMismatchResult;
use ABTests\Values\Segment;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * CQRS read side for the dashboard. Reads only the rollups table (never the
 * raw events table in the request path), runs the analysis engines, and returns
 * a fully-computed ExperimentResultsData DTO. Results are cached per experiment
 * key with a configurable TTL.
 */
final readonly class ResultsService
{
    public function __construct(
        private ExperimentRegistry $registry,
        private AnalysisService $analysisService,
    ) {
    }

    /**
     * Compute analysis results for the given experiment, or null if the
     * experiment does not exist or has no rollup data yet.
     *
     * Note: the rollups table is itself the pre-aggregated cache layer, so
     * no additional object-level caching is applied here. Serializing readonly
     * DTOs containing Eloquent models via PHP's serialize()/unserialize()
     * produces __PHP_Incomplete_Class on read-back and is unsafe.
     */
    public function forExperiment(string $experimentKey): ?ExperimentResultsData
    {
        return $this->compute($experimentKey);
    }

    private function compute(string $experimentKey): ?ExperimentResultsData
    {
        $model = ExperimentModel::query()->where('key', $experimentKey)->first();

        if ($model === null) {
            return null;
        }

        try {
            $definition = $this->registry->findByKey($experimentKey);
        } catch (Throwable) {
            $definition = $this->buildDefinitionFromRollups($experimentKey, $model);
        }

        if ($definition === null) {
            return null;
        }

        $rollups = RollupModel::query()
            ->where('experiment_key', $experimentKey)
            ->get()
            ->keyBy(static fn (RollupModel $rollup): string => "$rollup->variant_key|$rollup->metric_key");

        if ($rollups->isEmpty()) {
            return new ExperimentResultsData(
                definition: $definition,
                model: $model,
                variantResults: [],
                sampleRatioMismatch: $this->emptyMismatch(),
                activeGuardrailBreaches: new Collection(),
                computedAt: new DateTimeImmutable(),
            );
        }

        $primaryMetric = $this->primaryMetricKey($definition);
        $variantResults = [];
        $allSummaries = [];

        foreach ($definition->allocation->variants as $variant) {
            $rollupKey = "{$variant->key()}|$primaryMetric";
            $rollup = $rollups->get($rollupKey);

            if ($rollup === null) {
                continue;
            }

            $summary = $this->toMetricSummary($variant->key(), $rollup, $variant->isControl());
            $allSummaries[] = $summary;
        }

        if ($allSummaries === []) {
            return new ExperimentResultsData(
                definition: $definition,
                model: $model,
                variantResults: [],
                sampleRatioMismatch: $this->emptyMismatch(),
                activeGuardrailBreaches: new Collection(),
                computedAt: new DateTimeImmutable(),
            );
        }

        $controlSummary = null;

        foreach ($allSummaries as $summary) {
            if ($summary->variant->isControl()) {
                $controlSummary = $summary;
                break;
            }
        }

        foreach ($definition->allocation->variants as $variant) {
            $rollupKey = "{$variant->key()}|$primaryMetric";
            $rollup = $rollups->get($rollupKey);

            if ($rollup === null) {
                continue;
            }

            $summary = $this->toMetricSummary($variant->key(), $rollup, $variant->isControl());

            $verdictResult = null;

            if ($controlSummary !== null && ! $variant->isControl()) {
                $verdictResult = $this->analysisService->analyse(
                    definition: $definition,
                    control: $controlSummary,
                    treatment: $summary,
                    allSummaries: $allSummaries,
                );
            }

            $secondarySummaries = [];
            $guardrailSummaries = [];

            foreach ($definition->metrics as $binding) {
                if ($binding->role === MetricRole::primary) {
                    continue;
                }

                $metricRollupKey = "{$variant->key()}|{$binding->metric}";
                $metricRollup = $rollups->get($metricRollupKey);

                if ($metricRollup === null) {
                    continue;
                }

                $metricSummary = $this->toMetricSummary($variant->key(), $metricRollup, $variant->isControl());

                if ($binding->role === MetricRole::secondary) {
                    $secondarySummaries[] = $metricSummary;
                } elseif ($binding->role === MetricRole::guardrail) {
                    $guardrailSummaries[$binding->metric] = $metricSummary;
                }
            }

            $variantResults[] = new VariantResultData(
                variant: $variant,
                primaryMetricSummary: $summary,
                verdictResult: $verdictResult,
                secondaryMetricSummaries: $secondarySummaries,
                guardrailSummaries: $guardrailSummaries,
            );
        }

        $srm = count($allSummaries) >= 2
            ? $this->analysisService->analyse(
                definition: $definition,
                control: $allSummaries[0],
                treatment: $allSummaries[1],
                allSummaries: $allSummaries,
            )->srm
            : $this->emptyMismatch();

        $activeBreaches = GuardrailBreachModel::query()
            ->where('experiment_key', $experimentKey)
            ->where('is_acknowledged', false)
            ->get();

        return new ExperimentResultsData(
            definition: $definition,
            model: $model,
            variantResults: $variantResults,
            sampleRatioMismatch: $srm,
            activeGuardrailBreaches: $activeBreaches,
            computedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Build a minimal ExperimentDefinition from rollup rows when the experiment
     * is not registered in the code registry (runtime-defined or DB-only).
     * Variant keys and metric keys are inferred from rollup data. The first
     * variant key encountered (alphabetically) is treated as control.
     */
    private function buildDefinitionFromRollups(string $experimentKey, ExperimentModel $model): ?ExperimentDefinition
    {
        $rollups = RollupModel::query()
            ->where('experiment_key', $experimentKey)
            ->get();

        if ($rollups->isEmpty()) {
            // No rollup data — return a definition with no variants so the
            // dashboard can still show the experiment header and controls.
            return new ExperimentDefinition(
                key: $experimentKey,
                unitType: 'unknown',
                allocation: Allocation::fromVariants([
                    new GenericVariant(key: 'control', weight: 100, isControl: true),
                ]),
                analysis: AnalysisConfiguration::default(),
                audience: Segment::any(),
                metrics: [],
                name: $model->key,
            );
        }

        $variantKeys = $rollups->pluck('variant_key')->unique()->sort()->values();
        $metricKeys  = $rollups->pluck('metric_key')->unique()->values();

        $variantCount = $variantKeys->count();
        $weight       = (int) floor(100 / $variantCount);
        $remainder    = 100 - ($weight * $variantCount);

        $variants = $variantKeys->map(static function (string $key, int $index) use ($weight, $remainder, $variantKeys): GenericVariant {
            $isControl     = $index === 0;
            $variantWeight = $isControl ? $weight + $remainder : $weight;

            return new GenericVariant(key: $key, weight: $variantWeight, isControl: $isControl);
        })->values()->all();

        $metrics = $metricKeys->map(static fn (string $key, int $index): MetricBinding => new MetricBinding(
            metric: $key,
            role: $index === 0 ? MetricRole::primary : MetricRole::secondary,
        ))->values()->all();

        return new ExperimentDefinition(
            key: $experimentKey,
            unitType: $model->layer ?? 'unknown',
            allocation: Allocation::fromVariants($variants),
            analysis: AnalysisConfiguration::default(),
            audience: Segment::any(),
            metrics: $metrics,
            name: $model->key,
        );
    }

    private function primaryMetricKey(ExperimentDefinition $definition): string
    {
        foreach ($definition->metrics as $binding) {
            if ($binding->role === MetricRole::primary) {
                return $binding->metric;
            }
        }

        return '';
    }

    private function toMetricSummary(
        string $variantKey,
        RollupModel|Model $rollup,
        bool $isControl,
    ): MetricSummary {
        return new MetricSummary(
            variant: new GenericVariant(
                key: $variantKey,
                weight: 0,
                isControl: $isControl,
            ),
            countOfUnits: $rollup->count_of_units,
            sumOfValues: $rollup->sum_of_values,
            sumOfSquaredValues: $rollup->sum_of_squared_values,
            conversions: $rollup->conversions,
        );
    }

    private function emptyMismatch(): SampleRatioMismatchResult
    {
        return new SampleRatioMismatchResult(
            detected: false,
            chiSquare: 0.0,
            pValue: 1.0,
        );
    }
}
