<?php

declare(strict_types=1);

namespace ABTests\Domain\Analysis;

use ABTests\Enums\StatisticalEngine;
use ABTests\Enums\Verdict;
use ABTests\Values\AnalysisConfiguration;
use ABTests\Values\AnalysisResult;
use ABTests\Values\SampleRatioMismatchResult;
use ABTests\Values\VerdictResult;

/**
 * Converts raw engine outputs into a human-readable shipping decision.
 *
 * Decision rules (applied in order):
 *
 *  1. If SRM is detected the experiment data is unreliable → inconclusive.
 *  2. If no engine produced a significant result → inconclusive.
 *  3. When both engines ran, they must agree on direction; disagreement → inconclusive.
 *  4. Positive significant lift → ship; negative → doNotShip.
 */
final readonly class VerdictResolver
{
    public function resolve(
        ?AnalysisResult $frequentist,
        ?AnalysisResult $bayesian,
        SampleRatioMismatchResult $srm,
        AnalysisConfiguration $configuration,
    ): VerdictResult {
        if ($srm->detected) {
            return new VerdictResult(
                verdict: Verdict::inconclusive,
                frequentist: $frequentist,
                bayesian: $bayesian,
                srm: $srm,
            );
        }

        $primaryResult = $this->selectPrimary($frequentist, $bayesian, $configuration);

        if ($primaryResult === null || ! $primaryResult->isSignificant) {
            return new VerdictResult(
                verdict: Verdict::inconclusive,
                frequentist: $frequentist,
                bayesian: $bayesian,
                srm: $srm,
            );
        }

        // When both engines ran, require directional agreement.
        if ($frequentist !== null && $bayesian !== null) {
            $freqPositive = $frequentist->relativeLift > 0.0;
            $bayesPositive = $bayesian->relativeLift > 0.0;

            if ($freqPositive !== $bayesPositive) {
                return new VerdictResult(
                    verdict: Verdict::inconclusive,
                    frequentist: $frequentist,
                    bayesian: $bayesian,
                    srm: $srm,
                );
            }
        }

        $verdict = $primaryResult->relativeLift > 0.0 ? Verdict::ship : Verdict::doNotShip;

        return new VerdictResult(
            verdict: $verdict,
            frequentist: $frequentist,
            bayesian: $bayesian,
            srm: $srm,
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Pick the result that drives the significance decision based on the
     * configured engine preference.
     */
    private function selectPrimary(
        ?AnalysisResult $frequentist,
        ?AnalysisResult $bayesian,
        AnalysisConfiguration $configuration,
    ): ?AnalysisResult {
        return match ($configuration->engine) {
            StatisticalEngine::frequentist => $frequentist,
            StatisticalEngine::bayesian    => $bayesian,
            StatisticalEngine::both        => $this->bothSignificant($frequentist, $bayesian),
        };
    }

    /**
     * When engine = both, the primary result is the frequentist one, but only
     * if both engines agree the result is significant.
     */
    private function bothSignificant(
        ?AnalysisResult $frequentist,
        ?AnalysisResult $bayesian,
    ): ?AnalysisResult {
        if ($frequentist === null || $bayesian === null) {
            return $frequentist ?? $bayesian;
        }

        if (! $frequentist->isSignificant || ! $bayesian->isSignificant) {
            return null;
        }

        return $frequentist;
    }
}
