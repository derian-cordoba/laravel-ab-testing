<?php

declare(strict_types=1);

namespace ABTests\Contracts;

/**
 * Maps a unit to a stable position used to pick a variant. The default
 * implementation hashes a salt together with the unit's bucketing key, so
 * assignment is deterministic, sticky, and independent across experiments.
 * Swap this to change the hashing algorithm without touching domain logic.
 */
interface BucketingStrategy
{
    /**
     * Return a position in the half-open interval [0.0, 1.0). The same salt
     * and unit must always yield the same position.
     */
    public function position(string $salt, Bucketable $unit): float;
}
