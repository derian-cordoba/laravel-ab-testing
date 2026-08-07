<?php

declare(strict_types=1);

namespace ABTests\Values;

use DateTimeImmutable;

/**
 * Immutable snapshot of a persisted experiment row. Returned by ExperimentRepository
 * so application-layer handlers never hold a mutable Eloquent model.
 */
final readonly class ExperimentRecord
{
    /**
     * @param list<string>|null $allowedEnvironments
     */
    public function __construct(
        public int $id,
        public string $key,
        public ?string $name,
        public string $status,
        public ?string $layer,
        public ?array $allowedEnvironments,
        public int $trafficPercentage,
        public bool $isKilled,
        public ?DateTimeImmutable $killedAt,
        public ?DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $stoppedAt,
        public ?int $targetSampleSize,
    ) {}
}
