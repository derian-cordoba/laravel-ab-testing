<?php

declare(strict_types=1);

namespace ABTests\Contracts;

use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Values\ExperimentRecord;

interface ExperimentRepository
{
    public function findByKey(string $key): ?ExperimentRecord;

    /** @throws ExperimentNotFound */
    public function getByKey(string $key): ExperimentRecord;

    public function update(string $key, array $attributes): void;

    public function create(array $attributes): ExperimentRecord;

    public function deleteByKey(string $key): void;
}
