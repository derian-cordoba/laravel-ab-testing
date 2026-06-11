<?php

declare(strict_types=1);

namespace ABTests\Tests\Fixtures;

use ABTests\Attributes\AsMetric;
use ABTests\Enums\MetricType;
use ABTests\Metric;

#[AsMetric(key: 'test-conversion', type: MetricType::binary, event: 'conversion.completed')]
final class TestMetric extends Metric
{
}
