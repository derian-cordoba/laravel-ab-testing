<?php

declare(strict_types=1);

namespace ABTests\Strategies;

use ABTests\Contracts\Bucketable;
use ABTests\Contracts\BucketingStrategy;

/**
 * Default bucketing strategy. Hashes the salt and the unit's bucketing key
 * with SHA-256, takes the first four bytes as an unsigned 32-bit integer, and
 * maps it to the half-open interval [0.0, 1.0).
 *
 * Properties that make this suitable for A/B assignment:
 *   • Deterministic — same salt + same unit always yields the same position.
 *   • Independent across experiments — different experiment keys (salts) produce
 *     uncorrelated assignments for the same unit.
 *   • Uniform — SHA-256 output passes all standard uniformity tests, giving
 *     each variant its fair share of traffic.
 *
 * The salt passed in is the experiment key. If the experiment is versioned, the
 * version can be incorporated in the key to re-randomize without touching the
 * algorithm.
 */
final readonly class Sha256BucketingStrategy implements BucketingStrategy
{
    private const int UINT32_MAX = 0xFFFF_FFFF;

    public function position(string $salt, Bucketable $unit): float
    {
        $input = "$salt:{$unit->bucketingKey()}";

        // SHA-256 raw binary; take the first 4 bytes as a big-endian uint32.
        $hash = hash('sha256', $input, binary: true);

        /** @var array{1: int} $unpacked */
        $unpacked = unpack('N', substr($hash, 0, 4));

        return $unpacked[1] / self::UINT32_MAX;
    }
}
