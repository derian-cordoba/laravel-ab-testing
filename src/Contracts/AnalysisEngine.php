<?php

declare(strict_types=1);

namespace ABTests\Contracts;

use ABTests\Values\AnalysisConfiguration;
use ABTests\Values\AnalysisResult;
use ABTests\Values\MetricSummary;

/**
 * Compares a treatment arm against control for one metric. Frequentist and
 * Bayesian engines each implement this; an experiment may run one or both.
 * Engines receive pre-aggregated sufficient statistics, never raw events.
 */
interface AnalysisEngine
{
    public function compare(
        MetricSummary $control,
        MetricSummary $treatment,
        AnalysisConfiguration $configuration,
    ): AnalysisResult;
}
