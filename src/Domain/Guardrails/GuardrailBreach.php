<?php

declare(strict_types=1);

namespace ABTests\Domain\Guardrails;

/**
 * Describes a single guardrail threshold violation detected by GuardrailEvaluationService.
 */
final readonly class GuardrailBreach
{
    public function __construct(
        public string $metricKey,
        public string $variantKey,
        public float $observedValue,
        public float $thresholdValue,
    ) {}
}
