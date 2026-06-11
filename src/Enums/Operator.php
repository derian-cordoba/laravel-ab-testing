<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * Comparison operators available to segment targeting rules.
 */
enum Operator: string
{
    case equals = 'equals';
    case notEquals = 'not_equals';
    case in = 'in';
    case notIn = 'not_in';
    case greaterThan = 'greater_than';
    case lessThan = 'less_than';

    public function label(): string
    {
        return match ($this) {
            self::equals      => 'equals',
            self::notEquals   => 'does not equal',
            self::in          => 'is one of',
            self::notIn       => 'is not one of',
            self::greaterThan => 'greater than',
            self::lessThan    => 'less than',
        };
    }
}
