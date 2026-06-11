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
            self::ship        => 'Ship',
            self::doNotShip   => 'Do not ship',
            self::inconclusive => 'Inconclusive',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ship         => 'bg-green-900/60 text-green-300',
            self::doNotShip    => 'bg-red-900/60 text-red-300',
            self::inconclusive => 'bg-yellow-900/60 text-yellow-300',
        };
    }
}
