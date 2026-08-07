<?php

declare(strict_types=1);

namespace ABTests\Domain\Analysis;

use ABTests\Contracts\AnalysisEngine;
use ABTests\Contracts\CovariateRepository;
use ABTests\Definitions\ExperimentDefinition;
use ABTests\Enums\StatisticalEngine;
use ABTests\Values\MetricSummary;
use ABTests\Values\VerdictResult;

/**
 * Orchestrates a full analysis run for one (control, treatment) pair.
 *
 * Given pre-aggregated MetricSummary objects it:
 *  1. Optionally applies CUPED variance reduction when pre-experiment covariate
 *     data is available for the metric.
 *  2. Runs the engine(s) specified in ExperimentDefinition::$analysis.
 *  3. Checks for sample-ratio mismatch.
 *  4. Delegates to VerdictResolver for the final ship / doNotShip / inconclusive
 *     decision.
 *
 * All engines are injected so they can be replaced or faked in tests without
 * touching this orchestration logic.
 */
final readonly class AnalysisService
{
    public function __construct(
        private AnalysisEngine $frequentistEngine,
        private AnalysisEngine $bayesianEngine,
        private SampleRatioMismatchDetector $srmDetector,
        private VerdictResolver $verdictResolver,
        private CovariateRepository $covariateRepository,
    ) {
        //
    }

    /**
     * Analyze a single treatment arm against control.
     *
     * @param  list<MetricSummary>  $allSummaries  All variant summaries for SRM detection
     *                                             (include both control and treatment).
     * @param  string|null  $metricKey  When provided, CUPED covariate data is
     *                                  looked up for this metric and the
     *                                  experiment key derived from the definition.
     */
    public function analyse(
        ExperimentDefinition $definition,
        MetricSummary $control,
        MetricSummary $treatment,
        array $allSummaries,
        ?string $metricKey = null,
    ): VerdictResult {
        // Apply CUPED if covariate data is available for this metric.
        if ($metricKey !== null) {
            [$control, $treatment, $allSummaries] = $this->applyRatioReduction(
                $definition->key,
                $metricKey,
                $control,
                $treatment,
                $allSummaries,
            );
        }

        $configuration = $definition->analysis;
        $engine = $configuration->engine;

        $frequentistResult = null;
        $bayesianResult = null;

        if ($engine === StatisticalEngine::frequentist || $engine === StatisticalEngine::both) {
            $frequentistResult = $this->frequentistEngine->compare($control, $treatment, $configuration);
        }

        if ($engine === StatisticalEngine::bayesian || $engine === StatisticalEngine::both) {
            $bayesianResult = $this->bayesianEngine->compare($control, $treatment, $configuration);
        }

        $srm = $this->srmDetector->detect($allSummaries, $definition->allocation);

        return $this->verdictResolver->resolve(
            frequentist: $frequentistResult,
            bayesian: $bayesianResult,
            srm: $srm,
            configuration: $configuration,
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Apply CUPED variance reduction when covariate rows exist for the metric.
     * Returns the (possibly adjusted) control, treatment, and full allSummaries.
     *
     * @param  list<MetricSummary>  $allSummaries
     * @return array{0: MetricSummary, 1: MetricSummary, 2: list<MetricSummary>}
     */
    private function applyRatioReduction(
        string $experimentKey,
        string $metricKey,
        MetricSummary $control,
        MetricSummary $treatment,
        array $allSummaries,
    ): array {
        // Check cheaply whether any covariate rows exist before instantiating CUPED.
        if (! $this->covariateRepository->hasAny($experimentKey, $metricKey)) {
            return [$control, $treatment, $allSummaries];
        }

        $covStats = $this->covariateRepository->loadStatsPerVariant($experimentKey, $metricKey);
        $globalMean = $this->covariateRepository->globalMean($experimentKey, $metricKey);

        $cuped = new CupedVarianceReduction();
        $adjustedSummaries = $cuped->adjust($allSummaries, $covStats, $globalMean);

        // Re-locate control and treatment in the adjusted array (same order, same variant).
        $controlKey = $control->variant->key();
        $treatmentKey = $treatment->variant->key();

        $adjustedControl = $control;
        $adjustedTreatment = $treatment;

        foreach ($adjustedSummaries as $adjusted) {
            if ($adjusted->variant->key() === $controlKey) {
                $adjustedControl = $adjusted;
            }

            if ($adjusted->variant->key() === $treatmentKey) {
                $adjustedTreatment = $adjusted;
            }
        }

        return [$adjustedControl, $adjustedTreatment, $adjustedSummaries];
    }
}
