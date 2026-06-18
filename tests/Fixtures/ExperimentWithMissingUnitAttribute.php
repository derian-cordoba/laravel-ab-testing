<?php

declare(strict_types=1);

namespace ABTests\Tests\Fixtures;

use ABTests\Attributes\AsExperiment;
use ABTests\Attributes\PrimaryMetric;
use ABTests\Experiment;

#[AsExperiment(
    key: 'experiment-with-missing-unit-attribute',
    unit: UndeclaredUnitType::class,
    variants: TestVariant::class,
)]
#[PrimaryMetric(TestMetric::class)]
final class ExperimentWithMissingUnitAttribute extends Experiment {
    //
}
