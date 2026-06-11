<?php

declare(strict_types=1);

namespace ABTests\Definitions;

/**
 * The normalized, framework-agnostic representation of a feature flag. Produced
 * by AttributeReader from a #[AsFeatureFlag]-decorated class and registered in
 * FeatureFlagRegistry. Everything downstream (resolver, dashboard) reads only
 * this value object — never the original attribute or class.
 */
final readonly class FeatureFlagDefinition
{
    /**
     * @param string $key       Stable kebab-case identifier.
     * @param string $unitType  The unit's stable type key (e.g. "user").
     * @param mixed  $defaultValue Returned when resolution cannot complete.
     * @param string|null $name Human-readable display name (optional).
     */
    public function __construct(
        public string $key,
        public string $unitType,
        public mixed $defaultValue = false,
        public ?string $name = null,
    ) {
        //
    }
}
