<?php

declare(strict_types=1);

namespace ABTests\Presentation\Support;

use ABTests\Enums\Verdict;

final class VerdictPresenter
{
    public static function badgeClass(Verdict $verdict): string
    {
        return match ($verdict) {
            Verdict::ship         => 'bg-green-900/60 text-green-300',
            Verdict::doNotShip    => 'bg-red-900/60 text-red-300',
            Verdict::inconclusive => 'bg-yellow-900/60 text-yellow-300',
        };
    }
}
