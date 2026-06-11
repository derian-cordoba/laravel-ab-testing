<?php

declare(strict_types=1);

namespace ABTests\Exceptions;

use RuntimeException;

/**
 * Thrown when a resolution or lookup references an experiment key or class
 * that has not been registered in the ExperimentRegistry.
 */
final class ExperimentNotFound extends RuntimeException implements ABTestingException
{
    public function __construct(string $identifier)
    {
        parent::__construct(
            "Experiment [{$identifier}] is not registered. Did you add it to the registry or run php artisan ab:cache?"
        );
    }
}
