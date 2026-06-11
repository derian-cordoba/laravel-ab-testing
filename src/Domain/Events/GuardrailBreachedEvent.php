<?php

declare(strict_types=1);

namespace ABTests\Domain\Events;

final readonly class GuardrailBreachedEvent
{
    public function __construct(
        public string $experimentKey,
        public string $metricKey,
        public string $variantKey,
        public float $observedValue,
        public float $thresholdValue,
    ) {
        //
    }
}
