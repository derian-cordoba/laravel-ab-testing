<?php

declare(strict_types=1);

namespace ABTests\Statistics;

use ABTests\Contracts\AnalysisEngine;
use ABTests\Definitions\ExperimentDefinition;
use ABTests\Enums\StatisticalEngine;
use ABTests\Values\MetricSummary;
use ABTests\Values\VerdictResult;

/**
 * Orchestrates a full analysis run for one (control, treatment) pair.
 *
 * Given pre-aggregated MetricSummary objects it:
 *  1. Runs the engine(s) specified in ExperimentDefinition::$analysis.
 *  2. Checks for sample-ratio mismatch.
 *  3. Delegates to VerdictResolver for the final ship / doNotShip / inconclusive
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
    ) {
    }

    /**
     * Analyze a single treatment arm against control.
     *
     * @param list<MetricSummary> $allSummaries All variant summaries for SRM detection
     *                                          (include both control and treatment).
     */
    public function analyse(
        ExperimentDefinition $definition,
        MetricSummary $control,
        MetricSummary $treatment,
        array $allSummaries,
    ): VerdictResult {
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
}
