<?php

declare(strict_types=1);

namespace ABTests\Contracts;

use ABTests\Definitions\ExperimentDefinition;

/**
 * Resolves a Bucketable unit to the Variant it should experience for a given
 * experiment. The default implementation runs the full resolution pipeline;
 * the testing fake checks forced overrides before falling through to null.
 */
interface ResolvesVariant
{
    public function resolve(ExperimentDefinition $definition, Bucketable $unit): ?Variant;
}
