<?php

declare(strict_types=1);

namespace ABTests\Tests\Fixtures;

use ABTests\Attributes\AsExperiment;
use ABTests\Attributes\PrimaryMetric;
use ABTests\Experiment;

#[AsExperiment(
    key: CheckoutExperimentKey::checkoutButtonColor,
    unit: BackedEnumUnitType::class,
    variants: TestVariant::class,
)]
#[PrimaryMetric(TestMetric::class)]
final class BackedEnumExperiment extends Experiment {}
