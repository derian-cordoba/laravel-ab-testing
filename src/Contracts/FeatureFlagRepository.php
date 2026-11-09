<?php

declare(strict_types=1);

namespace ABTests\Contracts;

use ABTests\Exceptions\FeatureFlagNotFound;
use ABTests\Values\FeatureFlagRecord;

interface FeatureFlagRepository
{
    public function findByKey(string $key): ?FeatureFlagRecord;

    /** @throws FeatureFlagNotFound */
    public function getByKey(string $key): FeatureFlagRecord;

    public function update(string $key, array $attributes): void;

    public function updateQuietly(string $key, array $attributes): void;

    public function create(array $attributes): FeatureFlagRecord;

    public function deleteByKey(string $key): void;
}
