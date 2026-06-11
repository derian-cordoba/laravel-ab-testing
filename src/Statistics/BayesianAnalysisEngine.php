<?php

declare(strict_types=1);

namespace ABTests\Statistics;

use ABTests\Contracts\AnalysisEngine;
use ABTests\Enums\StatisticalEngine;
use ABTests\Statistics\Support\MathFunctions;
use ABTests\Values\AnalysisConfiguration;
use ABTests\Values\AnalysisResult;
use ABTests\Values\MetricSummary;

/**
 * Normal–Normal conjugate Bayesian engine. Places a weakly informative
 * Gaussian prior on the true effect δ ~ N(0, τ²) with τ = 1.0 and updates
 * analytically given the observed sufficient statistics.
 *
 * Produces three posterior summaries per run:
 *
 *  - probabilityToBeatControl — P(δ > 0 | data) = Φ(μ_post / σ_post)
 *  - expectedLoss — E[max(−δ, 0)] under the posterior; the expected cost of
 *    shipping a treatment that actually harms the metric.
 *  - interval — highest-density credible interval at the configured level.
 *
 * isSignificant is true when the credible interval excludes zero (i.e. when
 * the posterior is concentrated on one side).
 *
 * This formulation works for binary, count, and continuous metrics because it
 * operates on the Normal approximation of the likelihood, which is valid at the
 * sample sizes where experimentation conclusions are meaningful.
 */
final readonly class BayesianAnalysisEngine implements AnalysisEngine
{
    /** Prior standard deviation for the true treatment effect. */
    private const float PRIOR_TAU = 1.0;

    public function compare(
        MetricSummary $control,
        MetricSummary $treatment,
        AnalysisConfiguration $configuration,
    ): AnalysisResult {
        $delta = $treatment->mean - $control->mean;
        $alpha = $configuration->confidence->significanceThreshold;

        $varControl = $control->countOfUnits > 0
            ? max($control->variance, 0.0) / $control->countOfUnits
            : 0.0;

        $varTreatment = $treatment->countOfUnits > 0
            ? max($treatment->variance, 0.0) / $treatment->countOfUnits
            : 0.0;

        $varLikelihood = $varControl + $varTreatment;

        $relativeLift = $control->mean !== 0.0 ? $delta / abs($control->mean) : 0.0;

        if ($varLikelihood <= 0.0) {
            return new AnalysisResult(
                engine: StatisticalEngine::bayesian,
                relativeLift: $relativeLift,
                isSignificant: false,
                interval: [$delta, $delta],
                probabilityToBeatControl: $delta > 0.0 ? 1.0 : 0.0,
                expectedLoss: 0.0,
            );
        }

        // Normal–Normal posterior update.
        $tau2 = self::PRIOR_TAU ** 2;
        $varPost = ($tau2 * $varLikelihood) / ($tau2 + $varLikelihood);
        $muPost = $varPost * ($delta / $varLikelihood);
        $sdPost = sqrt($varPost);

        // P(treatment > control) = P(δ > 0 | data) = Φ(μ_post / σ_post).
        $probabilityToBeatControl = MathFunctions::normalCdf($muPost / $sdPost);

        // Expected loss = E[max(−δ, 0)] = −μ·Φ(−μ/σ) + σ·φ(−μ/σ).
        $zLoss = -$muPost / $sdPost;
        $expectedLoss = -$muPost * MathFunctions::normalCdf($zLoss)
            + $sdPost * MathFunctions::normalPdf($zLoss);

        // Equal-tailed credible interval.
        $zCritical = MathFunctions::normalQuantile(1.0 - $alpha / 2.0);
        $lower = $muPost - $zCritical * $sdPost;
        $upper = $muPost + $zCritical * $sdPost;

        // Significant when the credible interval excludes zero.
        $isSignificant = $lower > 0.0 || $upper < 0.0;

        return new AnalysisResult(
            engine: StatisticalEngine::bayesian,
            relativeLift: $relativeLift,
            isSignificant: $isSignificant,
            interval: [$lower, $upper],
            probabilityToBeatControl: $probabilityToBeatControl,
            expectedLoss: max($expectedLoss, 0.0),
        );
    }
}
