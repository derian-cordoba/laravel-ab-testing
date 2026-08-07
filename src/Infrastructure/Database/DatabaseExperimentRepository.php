<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database;

use ABTests\Contracts\ExperimentRepository;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Values\ExperimentRecord;

final readonly class DatabaseExperimentRepository implements ExperimentRepository
{
    public function findByKey(string $key): ?ExperimentRecord
    {
        /** @var ExperimentModel|null */
        $model = ExperimentModel::query()->firstWhere('key', $key);

        return $model !== null ? $this->toRecord($model) : null;
    }

    public function getByKey(string $key): ExperimentRecord
    {
        $model = ExperimentModel::query()->firstWhere('key', $key);

        if ($model === null) {
            throw new ExperimentNotFound($key);
        }

        return $this->toRecord($model);
    }

    public function update(string $key, array $attributes): void
    {
        ExperimentModel::query()->where('key', $key)->update($attributes);
    }

    public function create(array $attributes): ExperimentRecord
    {
        /** @var ExperimentModel */
        $model = ExperimentModel::query()->create($attributes);

        return $this->toRecord($model);
    }

    public function deleteByKey(string $key): void
    {
        ExperimentModel::query()->where('key', $key)->delete();
    }

    private function toRecord(ExperimentModel $model): ExperimentRecord
    {
        return new ExperimentRecord(
            id: $model->id,
            key: $model->key,
            name: $model->name,
            status: $model->status,
            layer: $model->layer,
            allowedEnvironments: $model->allowed_environments,
            trafficPercentage: $model->traffic_percentage,
            isKilled: (bool) $model->is_killed,
            killedAt: $model->killed_at?->toDateTimeImmutable(),
            startedAt: $model->started_at?->toDateTimeImmutable(),
            stoppedAt: $model->stopped_at?->toDateTimeImmutable(),
            targetSampleSize: $model->target_sample_size,
        );
    }
}
