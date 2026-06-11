<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database;

use ABTests\Contracts\ExperimentStateRepository;
use ABTests\Enums\ExperimentStatus;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Values\ExperimentState;

/**
 * Database-backed experiment state repository. Reads the ab_testing_experiments
 * table to return the current operational state for a given experiment key.
 *
 * Returns null when no row exists for the given key, which the resolver
 * interprets as "not running" (the experiment is unknown or not yet created
 * in the database by the dashboard).
 */
final class DatabaseExperimentStateRepository implements ExperimentStateRepository
{
    public function findState(string $experimentKey): ?ExperimentState
    {
        $row = ExperimentModel::query()->firstWhere('key', $experimentKey);

        if ($row === null) {
            return null;
        }

        return new ExperimentState(
            experimentKey: $row->key,
            status: ExperimentStatus::from($row->status),
            trafficPercentage: $row->traffic_percentage,
            isKilled: $row->is_killed,
        );
    }
}
