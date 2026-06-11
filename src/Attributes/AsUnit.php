<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;
use UnitEnum;

/**
 * Declares a class as an assignment unit (a Bucketable implementation) and
 * gives it a stable type key used to namespace assignments and events.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsUnit
{
    /** Stable type key, normalized from a string or enum case. */
    public string $key;

    /**
     * @param string|UnitEnum $key Stable type key used to namespace assignments and events.
     *                              Accepts a backed enum case (returns its value) or a unit
     *                              enum case (returns its name), following the same semantics
     *                              as Laravel's enum_value().
     */
    public function __construct(string|UnitEnum $key)
    {
        $this->key = enum_value($key);
    }
}
