<?php

declare(strict_types=1);

namespace ABTests\Contracts;

use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Values\ExperimentRecord;
use ABTests\Values\VariantSnapshot;

interface ExperimentRepository
{
    public function findByKey(string $key): ?ExperimentRecord;

    /** @throws ExperimentNotFound */
    public function getByKey(string $key): ExperimentRecord;

    public function update(string $key, array $attributes): void;

    public function create(array $attributes): ExperimentRecord;

    public function deleteByKey(string $key): void;

    /**
     * Return variant weight/control snapshots for a given experiment key.
     * Used by the read-side (ResultsService) to reconstruct definitions for
     * experiments no longer present in the code registry.
     *
     * @return list<VariantSnapshot>
     */
    public function findVariants(string $experimentKey): array;
}
