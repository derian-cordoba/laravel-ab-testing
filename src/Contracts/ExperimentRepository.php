<?php

declare(strict_types=1);

namespace ABTests\Contracts;

use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Infrastructure\Database\Models\ExperimentModel;

interface ExperimentRepository
{
    public function findByKey(string $key): ?ExperimentModel;

    /** @throws ExperimentNotFound */
    public function getByKey(string $key): ExperimentModel;

    public function update(string $key, array $attributes): void;

    public function create(array $attributes): ExperimentModel;

    public function deleteByKey(string $key): void;
}
