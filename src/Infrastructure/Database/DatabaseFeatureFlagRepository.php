<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database;

use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Enums\ConditionsLogic;
use ABTests\Exceptions\FeatureFlagNotFound;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Values\FeatureFlagRecord;

final readonly class DatabaseFeatureFlagRepository implements FeatureFlagRepository
{
    public function findByKey(string $key): ?FeatureFlagRecord
    {
        /** @var FeatureFlagStateModel|null */
        $model = FeatureFlagStateModel::query()->firstWhere('key', $key);

        return $model !== null ? $this->toRecord($model) : null;
    }

    public function getByKey(string $key): FeatureFlagRecord
    {
        $model = FeatureFlagStateModel::query()->firstWhere('key', $key);

        if ($model === null) {
            throw new FeatureFlagNotFound("Feature flag [$key] not found.");
        }

        return $this->toRecord($model);
    }

    public function update(string $key, array $attributes): void
    {
        FeatureFlagStateModel::query()->where('key', $key)->update($attributes);
    }

    public function updateQuietly(string $key, array $attributes): void
    {
        FeatureFlagStateModel::query()->where('key', $key)->update($attributes);
    }

    public function create(array $attributes): FeatureFlagRecord
    {
        /** @var FeatureFlagStateModel */
        $model = FeatureFlagStateModel::query()->create($attributes);

        return $this->toRecord($model);
    }

    public function deleteByKey(string $key): void
    {
        FeatureFlagStateModel::query()->where('key', $key)->delete();
    }

    private function toRecord(FeatureFlagStateModel $model): FeatureFlagRecord
    {
        return new FeatureFlagRecord(
            key: $model->key,
            isEnabled: (bool) $model->is_enabled,
            isKilled: $model->killed_at !== null,
            rolloutPercentage: (int) $model->rollout_percentage,
            conditions: $model->conditions,
            conditionsLogic: $model->conditions_logic instanceof ConditionsLogic
                ? $model->conditions_logic
                : ConditionsLogic::all,
            allowedEnvironments: $model->allowed_environments,
        );
    }
}
