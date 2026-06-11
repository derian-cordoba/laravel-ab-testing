<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;

/**
 * Marks a variant enum case as the control (baseline) arm. Exactly one case
 * per experiment must carry this attribute.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Control
{
    //
}
