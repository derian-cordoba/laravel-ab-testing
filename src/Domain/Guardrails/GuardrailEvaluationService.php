<?php

declare(strict_types=1);

namespace ABTests\Domain\Guardrails;

use ABTests\Definitions\MetricBinding;

/**
 * Pure domain service that evaluates guardrail thresholds against rollup data.
 *
 * Receives pre-loaded rollup summaries as plain value objects (no DB access) and
 * returns the list of threshold violations. Persistence and event dispatching are
 * left to the infrastructure layer (RefreshRollupsJob).
 *
 * A breach is detected when:
 *   relativeRegression = (controlRate - treatmentRate) / controlRate > maximumRegression
 */
final class GuardrailEvaluationService
{
    /**
     * @param  list<MetricBinding>  $guardrails
     * @param  list<RollupSummary>  $summaries
     * @return list<GuardrailBreach>
     */
    public function evaluate(
        array $guardrails,
        array $summaries,
        string $controlVariantKey,
    ): array {
        $breaches = [];

        foreach ($guardrails as $guardrail) {
            $forMetric = array_filter(
                $summaries,
                static fn (RollupSummary $s): bool => $s->metricKey === $guardrail->metric,
            );

            $controlSummary = null;
            foreach ($forMetric as $summary) {
                if ($summary->variantKey === $controlVariantKey) {
                    $controlSummary = $summary;
                    break;
                }
            }

            if ($controlSummary === null || $controlSummary->countOfUnits === 0) {
                continue;
            }

            $controlRate = $controlSummary->conversions / $controlSummary->countOfUnits;

            if ($controlRate === 0.0) {
                continue;
            }

            $maximumRegression = $guardrail->maximumRegression ?? 0.0;

            foreach ($forMetric as $summary) {
                if ($summary->variantKey === $controlVariantKey || $summary->countOfUnits === 0) {
                    continue;
                }

                $treatmentRate = $summary->conversions / $summary->countOfUnits;
                $relativeRegression = ($controlRate - $treatmentRate) / $controlRate;

                if ($relativeRegression > $maximumRegression) {
                    $breaches[] = new GuardrailBreach(
                        metricKey: $guardrail->metric,
                        variantKey: $summary->variantKey,
                        observedValue: $relativeRegression,
                        thresholdValue: $maximumRegression,
                    );
                }
            }
        }

        return $breaches;
    }
}
