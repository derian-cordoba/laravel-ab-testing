<?php

declare(strict_types=1);

namespace ABTests\Application\Data;

use ABTests\Contracts\Variant;
use ABTests\Values\MetricSummary;
use ABTests\Values\VerdictResult;

final readonly class VariantResultData
{
    /**
     * @param list<MetricSummary> $secondaryMetricSummaries
     * @param array<string, MetricSummary> $guardrailSummaries Keyed by metric key.
     */
    public function __construct(
        public Variant $variant,
        public MetricSummary $primaryMetricSummary,
        public ?VerdictResult $verdictResult,
        public array $secondaryMetricSummaries,
        public array $guardrailSummaries,
    ) {
        //
    }
}
