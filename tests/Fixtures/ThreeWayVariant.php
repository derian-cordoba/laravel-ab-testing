<?php

declare(strict_types=1);

namespace ABTests\Tests\Fixtures;

use ABTests\Attributes\Control;
use ABTests\Attributes\Weight;
use ABTests\Concerns\IsVariant;
use ABTests\Contracts\Variant;

enum ThreeWayVariant: string implements Variant
{
    use IsVariant;

    #[Control]
    #[Weight(34)]
    case control = 'control';

    #[Weight(33)]
    case variantA = 'variant_a';

    #[Weight(33)]
    case variantB = 'variant_b';
}
