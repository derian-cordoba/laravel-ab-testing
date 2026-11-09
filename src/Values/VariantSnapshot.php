<?php

declare(strict_types=1);

namespace ABTests\Values;

/**
 * Immutable snapshot of a variant's DB-persisted weight and control flag.
 * Used by ResultsService to reconstruct ExperimentDefinitions for experiments
 * that are no longer registered in code (e.g. runtime-defined or archived).
 */
final readonly class VariantSnapshot
{
    public function __construct(
        public string $key,
        public int $weight,
        public bool $isControl,
    ) {}
}
