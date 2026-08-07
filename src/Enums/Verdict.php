<?php

declare(strict_types=1);

namespace ABTests\Enums;

enum Verdict: string
{
    case ship = 'ship';
    case doNotShip = 'do_not_ship';
    case inconclusive = 'inconclusive';

    public function label(): string
    {
        return match ($this) {
            self::ship => 'Ship',
            self::doNotShip => 'Do not ship',
            self::inconclusive => 'Inconclusive',
        };
    }

}
