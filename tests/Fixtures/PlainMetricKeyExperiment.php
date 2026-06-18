<?php

declare(strict_types=1);

namespace ABTests\Tests\Fixtures;

use ABTests\Attributes\AsExperiment;
use ABTests\Attributes\Guardrail;
use ABTests\Attributes\PrimaryMetric;
use ABTests\Experiment;

#[AsExperiment(
    key: 'plain-metric-key-experiment',
    unit: TestUnitType::class,
    variants: TestVariant::class,
)]
#[PrimaryMetric('signup-completed')]
#[Guardrail('payment-failure-rate', maximumRegression: 0.02)]
final class PlainMetricKeyExperiment extends Experiment {}
