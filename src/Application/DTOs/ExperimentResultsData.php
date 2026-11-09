<?php

declare(strict_types=1);

namespace ABTests\Application\DTOs;

use ABTests\Definitions\ExperimentDefinition;
use ABTests\Infrastructure\Database\Models\GuardrailBreachModel;
use ABTests\Values\ExperimentRecord;
use ABTests\Values\SampleRatioMismatchResult;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

final readonly class ExperimentResultsData
{
    /**
     * @param  list<VariantResultData>  $variantResults
     * @param  Collection<int, GuardrailBreachModel>  $activeGuardrailBreaches
     */
    public function __construct(
        public ExperimentDefinition $definition,
        public ExperimentRecord $record,
        public array $variantResults,
        public SampleRatioMismatchResult $sampleRatioMismatch,
        public Collection $activeGuardrailBreaches,
        public DateTimeImmutable $computedAt,
    ) {
        //
    }

    public function hasResults(): bool
    {
        return $this->variantResults !== [];
    }

    public function totalAssignedUnits(): int
    {
        $total = 0;

        foreach ($this->variantResults as $variantResult) {
            $total += $variantResult->primaryMetricSummary->countOfUnits;
        }

        return $total;
    }
}
