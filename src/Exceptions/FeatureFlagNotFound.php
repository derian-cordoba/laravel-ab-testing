<?php

declare(strict_types=1);

namespace ABTests\Exceptions;

use RuntimeException;

/**
 * Thrown when a lookup references a feature flag key or class that has not
 * been registered in the FeatureFlagRegistry.
 */
final class FeatureFlagNotFound extends RuntimeException implements ABTestingException
{
    public function __construct(string $identifier)
    {
        parent::__construct(
            "Feature flag [$identifier] is not registered. Did you add it to config/ab-testing.php or run php artisan ab:cache?",
        );
    }
}
