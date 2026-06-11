<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Contracts\Variant;

/**
 * Pre-aggregated sufficient statistics for one variant and one metric. These
 * are exactly what both engines need, so the analysis layer never rescans the
 * raw event stream. Derived figures are virtual properties (PHP 8.4 hooks).
 */
final class MetricSummary
{
    public function __construct(
        public readonly Variant $variant,
        public readonly int $countOfUnits,
        public readonly float $sumOfValues,
        public readonly float $sumOfSquaredValues,
        public readonly int $conversions,
    ) {
        //
    }

    public float $mean {
        get => $this->countOfUnits > 0
            ? $this->sumOfValues / $this->countOfUnits
            : 0.0;
    }

    public float $variance {
        get {
            if ($this->countOfUnits < 2) {
                return 0.0;
            }

            $mean = $this->mean;

            return ($this->sumOfSquaredValues / $this->countOfUnits) - ($mean * $mean);
        }
    }

    public float $conversionRate {
        get => $this->countOfUnits > 0
            ? $this->conversions / $this->countOfUnits
            : 0.0;
    }
}
