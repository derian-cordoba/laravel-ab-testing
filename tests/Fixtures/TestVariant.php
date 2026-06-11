<?php

declare(strict_types=1);

namespace ABTests\Tests\Fixtures;

use ABTests\Attributes\Control;
use ABTests\Attributes\Weight;
use ABTests\Concerns\IsVariant;
use ABTests\Contracts\Variant;

enum TestVariant: string implements Variant
{
    use IsVariant;

    #[Control]
    #[Weight(50)]
    case control = 'control';

    #[Weight(50)]
    case treatment = 'treatment';
}
