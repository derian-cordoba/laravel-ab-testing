<?php

declare(strict_types=1);

namespace ABTests\Contracts;

use ABTests\Definitions\ExperimentDefinition;
use ABTests\Values\EvaluationResult;

/**
 * Resolves a Bucketable unit to an EvaluationResult for a given experiment.
 * The result carries the resolved variant (or null) along with the reason the
 * pipeline produced that outcome.
 *
 * The default implementation runs the full resolution pipeline; the testing
 * fake checks forced overrides before returning an override result.
 *
 * @param  bool  $dryRun  When true, the resolver must not persist a new assignment
 *                        even if the unit would otherwise be bucketed for the first
 *                        time. Used by read-only operations such as isEligible().
 */
interface ResolvesVariant
{
    public function resolve(ExperimentDefinition $definition, Bucketable $unit, bool $dryRun = false): EvaluationResult;
}
