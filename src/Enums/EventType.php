<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * The kind of recorded event in the append-only event store.
 */
enum EventType: string
{
    case exposure = 'exposure';     // the unit actually experienced its variant
    case conversion = 'conversion'; // the unit completed a goal
    case metric = 'metric';         // an arbitrary measured value (e.g. revenue)
}
