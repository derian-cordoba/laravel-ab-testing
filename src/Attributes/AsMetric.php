<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use ABTests\Enums\Aggregate;
use ABTests\Enums\MetricType;
use Attribute;
use UnitEnum;

/**
 * Declares a reusable metric. An experiment references metrics and assigns
 * them roles (primary / secondary / guardrail) via separate attributes; the
 * metric itself only knows how it is measured.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsMetric
{
    /** Stable, kebab-case identifier, normalized from a string or enum case. */
    public string $key;

    /**
     * @param  string|UnitEnum  $key  Stable, kebab-case identifier. Accepts a
     *                                backed enum case (returns its value) or a
     *                                unit enum case (returns its name), following
     *                                the same semantics as Laravel's enum_value().
     * @param  MetricType  $type  Selects the statistical test.
     * @param  string  $event  Name of the raw event that feeds this metric.
     * @param  Aggregate  $aggregate  How a unit's events collapse to one observation.
     * @param  string|null  $valueFromProperty  Event property to read for continuous metrics.
     * @param  string  $attributionWindow  Time from exposure within which an event counts.
     */
    public function __construct(
        string|UnitEnum $key,
        public MetricType $type,
        public string $event,
        public Aggregate $aggregate = Aggregate::uniqueUnits,
        public ?string $valueFromProperty = null,
        public string $attributionWindow = '7 days',
    ) {
        $this->key = enum_value($key);
    }
}
