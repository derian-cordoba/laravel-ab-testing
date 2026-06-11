<?php

declare(strict_types=1);

namespace ABTests\Tests\Fixtures;

use ABTests\Attributes\Analysis;
use ABTests\Attributes\AsExperiment;
use ABTests\Attributes\Guardrail;
use ABTests\Attributes\PrimaryMetric;
use ABTests\Attributes\SecondaryMetric;
use ABTests\Enums\StatisticalEngine;
use ABTests\Experiment;
use ABTests\Values\Segment;

#[AsExperiment(
    key: 'test-experiment',
    unit: TestUnitType::class,
    variants: TestVariant::class,
    name: 'Test Experiment',
    layer: 'test-layer',
)]
#[PrimaryMetric(TestMetric::class)]
#[SecondaryMetric(TestMetric::class)]
#[Guardrail(TestMetric::class, maximumRegression: 0.01)]
#[Analysis(engine: StatisticalEngine::both, confidenceLevel: 0.95, sequential: false)]
final class TestExperiment extends Experiment
{
    public function audience(): Segment
    {
        return Segment::where('plan', 'pro');
    }
}
