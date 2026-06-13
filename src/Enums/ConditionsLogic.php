<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * Determines how a feature flag's targeting conditions are combined.
 * "all" requires every condition to match (AND conjunction).
 * "any" requires at least one condition to match (OR disjunction).
 */
enum ConditionsLogic: string
{
    case all = 'all';
    case any = 'any';

    public function label(): string
    {
        return match ($this) {
            self::all => 'All conditions must match',
            self::any => 'Any condition must match',
        };
    }
}
