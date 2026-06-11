<?php

declare(strict_types=1);

namespace ABTests;

use ABTests\Attributes\AsMetric;
use ReflectionClass;

/**
 * Base class for metric definitions. Configuration (event, type, aggregate,
 * attribution window) is declared with #[AsMetric]. Override valueOf() only
 * when deriving a continuous value needs custom logic beyond reading a single
 * event property.
 */
abstract class Metric
{
    /**
     * Derive this metric's numeric contribution from a recorded event's
     * properties.
     *
     * Default behavior (resolved automatically from the #[AsMetric] attribute):
     *  - If the attribute declares `valueFromProperty`, the named property is
     *    read from $properties and cast to float. Returns 0.0 when the property
     *    is absent, so missing values are treated as zero rather than silently
     *    inflating the mean.
     *  - Otherwise returns 1.0, making the metric presence-based (binary).
     *
     * Subclasses may override this method when the value requires custom
     * derivation beyond reading a single event property.
     *
     * @param array<string, mixed> $properties
     */
    public function valueOf(array $properties): float
    {
        $attributes = new ReflectionClass(static::class)->getAttributes(AsMetric::class);

        if ($attributes !== []) {
            /** @var AsMetric $asMetric */
            $asMetric = $attributes[0]->newInstance();

            if ($asMetric->valueFromProperty !== null) {
                return isset($properties[$asMetric->valueFromProperty])
                    ? (float) $properties[$asMetric->valueFromProperty]
                    : 0.0;
            }
        }

        return 1.0;
    }
}
