<?php

declare(strict_types=1);

namespace ABTests\Contracts;

/**
 * The subject of an assignment: a user, a tenant, a session, a device.
 * This is the "multi-level" seam. An experiment declares which Bucketable
 * implementation it buckets on, so the same code can run user-level or
 * tenant-level experiments without change.
 */
interface Bucketable
{
    /**
     * A stable, globally unique identifier for this unit, used as the input
     * to deterministic bucketing. Must never change for a given subject.
     */
    public function bucketingKey(): string;

    /**
     * Attributes describing this unit, consumed by segment targeting.
     *
     * @return array<string, scalar|array<scalar>|null>
     */
    public function attributes(): array;
}
