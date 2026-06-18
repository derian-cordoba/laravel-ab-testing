<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Contracts\Bucketable;
use ABTests\Enums\Environment;

/**
 * The immutable resolution context handed to a flag's resolve() method. It
 * carries the unit, the environment, and the unit's already-computed bucket
 * position for this definition, so resolution stays a pure function.
 */
final readonly class Context
{
    /**
     * @param  float  $position  Bucket position in [0, 1) for the current definition.
     */
    public function __construct(
        public Bucketable $unit,
        public Environment $environment,
        public float $position,
    ) {}

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->unit->attributes()[$key] ?? $default;
    }

    /** True for the given percentage slice of units, stably and deterministically. */
    public function inRollout(int $percentage): bool
    {
        return $this->position < ($percentage / 100);
    }
}
