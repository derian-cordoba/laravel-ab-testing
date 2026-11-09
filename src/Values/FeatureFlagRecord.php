<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Enums\ConditionsLogic;

/**
 * Immutable snapshot of a feature flag's persisted operational state.
 * Decouples the Application and Domain layers from the Eloquent model.
 */
final readonly class FeatureFlagRecord
{
    /**
     * @param  list<string>|null                                                    $allowedEnvironments
     * @param  list<array{attribute:string,operator:string,expected:mixed}>|null  $conditions
     */
    public function __construct(
        public string $key,
        public bool $isEnabled,
        public bool $isKilled,
        public int $rolloutPercentage,
        public ?array $conditions,
        public ConditionsLogic $conditionsLogic,
        public ?array $allowedEnvironments,
    ) {}

    public function isActive(): bool
    {
        return $this->isEnabled && ! $this->isKilled;
    }
}
