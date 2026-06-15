<?php

declare(strict_types=1);

namespace ABTests\Infrastructure;

use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Exceptions\FeatureFlagNotFound;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;

/**
 * No-op feature flag repository. Returns null for all lookups and discards
 * all writes silently. Used in testing contexts where flag state is not
 * backed by a real database.
 */
final readonly class NullFeatureFlagRepository implements FeatureFlagRepository
{
    public function findByKey(string $key): ?FeatureFlagStateModel
    {
        return null;
    }

    public function getByKey(string $key): FeatureFlagStateModel
    {
        throw new FeatureFlagNotFound("Feature flag [$key] not found.");
    }

    public function update(string $key, array $attributes): void
    {
        // no-op
    }

    public function updateQuietly(string $key, array $attributes): void
    {
        // no-op
    }

    public function create(array $attributes): FeatureFlagStateModel
    {
        return new FeatureFlagStateModel($attributes);
    }

    public function deleteByKey(string $key): void
    {
        // no-op
    }
}
