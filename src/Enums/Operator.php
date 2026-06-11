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
}
