<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database;

use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Exceptions\FeatureFlagNotFound;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;

final readonly class DatabaseFeatureFlagRepository implements FeatureFlagRepository
{
    public function findByKey(string $key): ?FeatureFlagStateModel
    {
        /** @var FeatureFlagStateModel|null */
        return FeatureFlagStateModel::query()->firstWhere('key', $key);
    }

    public function getByKey(string $key): FeatureFlagStateModel
    {
        $model = $this->findByKey($key);

        if ($model === null) {
            throw new FeatureFlagNotFound("Feature flag [$key] not found.");
        }

        return $model;
    }

    public function update(string $key, array $attributes): void
    {
        FeatureFlagStateModel::query()->where('key', $key)->update($attributes);
    }

    public function updateQuietly(string $key, array $attributes): void
    {
        FeatureFlagStateModel::query()->where('key', $key)->update($attributes);
    }

    public function create(array $attributes): FeatureFlagStateModel
    {
        /** @var FeatureFlagStateModel */
        return FeatureFlagStateModel::query()->create($attributes);
    }

    public function deleteByKey(string $key): void
    {
        FeatureFlagStateModel::query()->where('key', $key)->delete();
    }
}
