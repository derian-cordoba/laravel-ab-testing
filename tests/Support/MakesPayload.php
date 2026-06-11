<?php

declare(strict_types=1);

namespace ABTests\Tests\Support;

use ABTests\Definitions\ExperimentDefinition;
use ABTests\Resolution\ResolutionPayload;
use ABTests\Tests\Fixtures\TestUnit;
use ABTests\Values\ExperimentState;

/**
 * Factory helper for building ResolutionPayload instances in pipeline step tests.
 */
trait MakesPayload
{
    use MakesDefinition;

    protected function makePayload(
        ?ExperimentDefinition $definition = null,
        ?TestUnit $unit = null,
        ?ExperimentState $state = null,
        float $bucketPosition = 0.3,
    ): ResolutionPayload {
        return new ResolutionPayload(
            definition: $definition ?? $this->makeDefinition(),
            unit: $unit ?? new TestUnit(),
            state: $state ?? ExperimentState::alwaysRunning('test-experiment'),
            bucketPosition: $bucketPosition,
        );
    }
}
