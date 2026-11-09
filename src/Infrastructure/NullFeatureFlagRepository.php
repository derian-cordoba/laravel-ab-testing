<?php

declare(strict_types=1);

namespace ABTests\Infrastructure;

use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Enums\ConditionsLogic;
use ABTests\Exceptions\FeatureFlagNotFound;
use ABTests\Values\FeatureFlagRecord;

/**
 * No-op feature flag repository. Returns null for all lookups and discards
 * all writes silently. Used in testing contexts where flag state is not
 * backed by a real database.
 */
final readonly class NullFeatureFlagRepository implements FeatureFlagRepository
{
    public function findByKey(string $key): ?FeatureFlagRecord
    {
        return null;
    }

    public function getByKey(string $key): FeatureFlagRecord
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

    public function create(array $attributes): FeatureFlagRecord
    {
        return new FeatureFlagRecord(
            key: $attributes['key'] ?? '',
            isEnabled: (bool) ($attributes['is_enabled'] ?? false),
            isKilled: isset($attributes['killed_at']) && $attributes['killed_at'] !== null,
            rolloutPercentage: (int) ($attributes['rollout_percentage'] ?? 100),
            conditions: $attributes['conditions'] ?? null,
            conditionsLogic: $attributes['conditions_logic'] ?? ConditionsLogic::all,
            allowedEnvironments: $attributes['allowed_environments'] ?? null,
        );
    }

    public function deleteByKey(string $key): void
    {
        // no-op
    }
}
