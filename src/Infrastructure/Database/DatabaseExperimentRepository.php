<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database;

use ABTests\Contracts\ExperimentRepository;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Infrastructure\Database\Models\ExperimentModel;

final readonly class DatabaseExperimentRepository implements ExperimentRepository
{
    public function findByKey(string $key): ?ExperimentModel
    {
        /** @var ExperimentModel|null */
        return ExperimentModel::query()->firstWhere('key', $key);
    }

    public function getByKey(string $key): ExperimentModel
    {
        $model = $this->findByKey($key);

        if ($model === null) {
            throw new ExperimentNotFound($key);
        }

        return $model;
    }

    public function update(string $key, array $attributes): void
    {
        ExperimentModel::query()->where('key', $key)->update($attributes);
    }

    public function create(array $attributes): ExperimentModel
    {
        /** @var ExperimentModel */
        return ExperimentModel::query()->create($attributes);
    }

    public function deleteByKey(string $key): void
    {
        ExperimentModel::query()->where('key', $key)->delete();
    }
}
