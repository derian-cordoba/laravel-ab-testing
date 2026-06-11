<?php

declare(strict_types=1);

namespace ABTests\Contracts;

use ABTests\Values\ExperimentState;

/**
 * Fetches the operational state of an experiment from the database. The state
 * is driven from the dashboard and controls whether the experiment is live,
 * how much traffic it receives, and whether its kill switch is active.
 *
 * A null return means no operational record exists for the given key, which
 * the resolver treats as "not running."
 */
interface ExperimentStateRepository
{
    /**
     * Fetches the current state for the experiment with the given key, or null if
     */
    public function findState(string $experimentKey): ?ExperimentState;
}
