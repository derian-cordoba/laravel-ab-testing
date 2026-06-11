<?php

declare(strict_types=1);

namespace ABTests\Infrastructure;

use ABTests\Contracts\ExperimentStateRepository;
use ABTests\Values\ExperimentState;

/**
 * Development/testing state repository that treats every experiment as running
 * at 100 % traffic with no kill switch applied. Bound by default in the service
 * provider so the package works out-of-the-box without a database. Replace with
 * the Eloquent implementation (shipping with the infrastructure layer) for
 * production use.
 */
final readonly class AlwaysRunningExperimentStateRepository implements ExperimentStateRepository
{
    public function findState(string $experimentKey): ExperimentState
    {
        return ExperimentState::alwaysRunning($experimentKey);
    }
}
