<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Values;

use ABTests\Tests\Fixtures\TestVariant;
use ABTests\Values\MetricSummary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MetricSummaryTest extends TestCase
{
    private function make(
        int $count,
        float $sum,
        float $sumSquared,
        int $conversions,
    ): MetricSummary {
        return new MetricSummary(
            variant: TestVariant::control,
            countOfUnits: $count,
            sumOfValues: $sum,
            sumOfSquaredValues: $sumSquared,
            conversions: $conversions,
        );
    }

    #[Test]
    public function mean_is_sum_divided_by_count(): void
    {
        $summary = $this->make(count: 4, sum: 20.0, sumSquared: 120.0, conversions: 2);

        self::assertEqualsWithDelta(5.0, $summary->mean(), 1e-10);
    }

    #[Test]
    public function mean_is_zero_when_count_is_zero(): void
    {
        $summary = $this->make(count: 0, sum: 0.0, sumSquared: 0.0, conversions: 0);

        self::assertSame(0.0, $summary->mean());
    }

    #[Test]
    public function variance_is_computed_correctly(): void
    {
        // Values: [3, 5, 7, 5] → mean=5, E[X²]=108/4=27, var=27-25=2
        $summary = $this->make(count: 4, sum: 20.0, sumSquared: 108.0, conversions: 0);

        self::assertEqualsWithDelta(2.0, $summary->variance(), 1e-10);
    }

    #[Test]
    public function variance_is_zero_when_fewer_than_two_units(): void
    {
        $summary = $this->make(count: 1, sum: 5.0, sumSquared: 25.0, conversions: 1);

        self::assertSame(0.0, $summary->variance());
    }

    #[Test]
    public function conversion_rate_is_conversions_over_count(): void
    {
        $summary = $this->make(count: 100, sum: 0.0, sumSquared: 0.0, conversions: 35);

        self::assertEqualsWithDelta(0.35, $summary->conversionRate(), 1e-10);
    }

    #[Test]
    public function conversion_rate_is_zero_when_count_is_zero(): void
    {
        $summary = $this->make(count: 0, sum: 0.0, sumSquared: 0.0, conversions: 0);

        self::assertSame(0.0, $summary->conversionRate());
    }
}
