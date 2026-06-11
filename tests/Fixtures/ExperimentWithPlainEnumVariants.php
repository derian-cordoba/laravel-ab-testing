<?php

declare(strict_types=1);

namespace ABTests\Tests\Fixtures;

use ABTests\Attributes\AsExperiment;
use ABTests\Attributes\PrimaryMetric;
use ABTests\Experiment;

#[AsExperiment(
    key: 'experiment-with-plain-enum-variants',
    unit: TestUnitType::class,
    variants: PlainEnumVariant::class,
)]
#[PrimaryMetric(TestMetric::class)]
final class ExperimentWithPlainEnumVariants extends Experiment
{
}
