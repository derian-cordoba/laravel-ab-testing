<?php

declare(strict_types=1);

namespace ABTests\Domain\Guardrails;

/**
 * Plain-value snapshot of a single rollup row consumed by GuardrailEvaluationService.
 * Decouples the domain service from the Eloquent RollupModel.
 */
final readonly class RollupSummary
{
    public function __construct(
        public string $variantKey,
        public string $metricKey,
        public int $conversions,
        public int $countOfUnits,
    ) {}
}
