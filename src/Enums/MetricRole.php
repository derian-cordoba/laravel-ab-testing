<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * The part a metric plays within an experiment.
 */
enum MetricRole: string
{
    case primary = 'primary';     // drives the ship / do-not-ship decision (exactly one)
    case secondary = 'secondary'; // observed for context, not decisive
    case guardrail = 'guardrail'; // must not regress beyond an allowed amount
}
