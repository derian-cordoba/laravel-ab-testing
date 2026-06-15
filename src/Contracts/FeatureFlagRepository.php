<?php

declare(strict_types=1);

namespace ABTests\Contracts;

use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;

interface FeatureFlagRepository
{
    public function findByKey(string $key): ?FeatureFlagStateModel;

    /** @throws \ABTests\Exceptions\FeatureFlagNotFound */
    public function getByKey(string $key): FeatureFlagStateModel;

    public function update(string $key, array $attributes): void;

    public function updateQuietly(string $key, array $attributes): void;

    public function create(array $attributes): FeatureFlagStateModel;

    public function deleteByKey(string $key): void;
}
