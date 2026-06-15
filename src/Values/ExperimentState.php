<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Enums\ExperimentStatus;

/**
 * The operational state of one experiment as stored in the database and driven
 * from the dashboard. Distinct from the structural definition (which lives in
 * code): status, traffic allocation, and kill switch can all change without
 * touching the codebase.
 */
final readonly class ExperimentState
{
    /**
     * @param list<string>|null $allowedEnvironments null = all environments (no restriction).
     */
    public function __construct(
        public string $experimentKey,
        public ExperimentStatus $status,
        public int $trafficPercentage,
        public bool $isKilled = false,
        public ?array $allowedEnvironments = null,
    ) {
        //
    }

    /**
     * Convenience factory for development and testing: returns a fully-live
     * state with 100 % traffic and no kill switch applied.
     */
    public static function alwaysRunning(string $experimentKey): self
    {
        return new self(
            experimentKey: $experimentKey,
            status: ExperimentStatus::running,
            trafficPercentage: 100,
        );
    }

    /**
     * An experiment is active when it is in the running lifecycle state and
     * the kill switch has not been pulled.
     */
    public function isActive(): bool
    {
        return ! $this->isKilled && $this->status->isLive();
    }
}
