<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

use DateTimeInterface;

/**
 * Records a pre-experiment metric value for one unit. Used to supply CUPED
 * covariates: record each unit's metric value from the reference period
 * (typically 7–30 days before the experiment starts) before you launch, so
 * the analysis engine can reduce variance at result-computation time.
 */
final readonly class RecordCovariateCommand
{
    public function __construct(
        public string $experimentKey,
        public string $metricKey,
        public string $unitType,
        public string $unitKey,
        public float $value,
        public ?DateTimeInterface $recordedAt = null,
    ) {
        //
    }
}
