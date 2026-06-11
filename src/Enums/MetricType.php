<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * The statistical nature of a metric. Determines which test the analysis
 * engine applies and how raw events are reduced to a single observation.
 */
enum MetricType: string
{
    case binary = 'binary';         // converted or not (proportion)
    case count = 'count';           // number of occurrences per unit
    case continuous = 'continuous'; // a real-valued measure per unit (revenue, duration)
    case ratio = 'ratio';           // numerator / denominator across units
}
