<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;
use UnitEnum;
use ABTests\Contracts\Bucketable;

/**
 * Declares a class as a feature flag definition. A flag controls exposure
 * (release management); it resolves to a value rather than measuring outcomes.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsFeatureFlag
{
    /** Stable, kebab-case identifier, normalised from a string or enum case. */
    public string $key;

    /**
     * @param string|UnitEnum          $key          Stable, kebab-case identifier. Accepts a
     *                                               backed enum case (returns its value) or a
     *                                               unit enum case (returns its name), following
     *                                               the same semantics as Laravel's enum_value().
     * @param class-string<Bucketable> $unit         Which subject this flag resolves for.
     * @param mixed                    $defaultValue Returned when resolution cannot complete
     *                                              (storage unavailable, unknown unit). Fail safe.
     */
    public function __construct(
        string|UnitEnum $key,
        public string $unit,
        public mixed $defaultValue = false,
    ) {
        $this->key = enum_value($key);
    }
}
