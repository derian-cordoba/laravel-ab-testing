<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * How a unit's raw events are collapsed into the single observation that
 * enters the statistics. Aggregation always happens at the unit level first.
 */
enum Aggregate: string
{
    case uniqueUnits = 'unique_units'; // a unit counts once if it ever converted
    case sum = 'sum';                  // total value across the unit's events
    case average = 'average';          // mean value across the unit's events
    case count = 'count';              // number of events for the unit
}
