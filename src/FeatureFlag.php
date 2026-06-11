<?php

declare(strict_types=1);

namespace ABTests;

use ABTests\Values\Context;

/**
 * Base class for feature flag definitions. A flag controls exposure and
 * resolves to a value. Structural configuration is declared with
 * #[AsFeatureFlag]; the resolution rule lives in resolve().
 */
abstract class FeatureFlag
{
    /**
     * Decide this flag's value for the given context. Must be a pure function
     * of the context so the result is deterministic and cacheable.
     */
    abstract public function resolve(Context $context): mixed;

    /**
     * Convenience for percentage rollouts inside resolve().
     */
    protected function rollout(int $percentage, Context $context): bool
    {
        return $context->inRollout($percentage);
    }
}
