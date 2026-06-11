<?php

declare(strict_types=1);

namespace ABTests\Enums;

enum Verdict: string
{
    case ship = 'ship';
    case doNotShip = 'do_not_ship';
    case inconclusive = 'inconclusive';
}
