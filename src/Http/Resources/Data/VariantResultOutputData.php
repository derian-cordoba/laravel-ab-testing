<?php

declare(strict_types=1);

namespace ABTests\Http\Resources\Data;

use ABTests\Application\Data\VariantResultData;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Serialization DTO for a single VariantResultData application object. Owns
 * the entire per-variant shape that appears in ExperimentResultsResource,
 * including the nested primary-metric snapshot and the optional verdict block.
 * Implements Arrayable so a Collection of these can be flattened via ->toArray()
 * without extra mapping in the resource.
 *
 * Usage in resources:
 *   VariantResultOutputData::fromCollection($data->variantResults)->toArray()
 */
final readonly class VariantResultOutputData implements Arrayable
{
    public function __construct(
        public string $key,
        public bool $isControl,
        public int $countOfUnits,
        public int $conversions,
        public float $conversionRate,
        public float $mean,
        public ?string $verdictRecommendation,
        public ?string $verdictLabel,
        public bool $verdictSrmDetected,
        public ?AnalysisResultData $frequentistAnalysis,
        public ?AnalysisResultData $bayesianAnalysis,
    ) {
        //
    }

    public static function from(VariantResultData $variantResult): self
    {
        $primary = $variantResult->primaryMetricSummary;
        $verdict = $variantResult->verdictResult;

        return new self(
            key: $variantResult->variant->key(),
            isControl: $variantResult->variant->isControl(),
            countOfUnits: $primary->countOfUnits,
            conversions: $primary->conversions,
            conversionRate: $primary->conversionRate,
            mean: $primary->mean,
            verdictRecommendation: $verdict?->verdict->value,
            verdictLabel: $verdict?->verdict->label(),
            verdictSrmDetected: $verdict?->srm->detected ?? false,
            frequentistAnalysis: $verdict?->frequentist !== null
                ? AnalysisResultData::from($verdict->frequentist)
                : null,
            bayesianAnalysis: $verdict?->bayesian !== null
                ? AnalysisResultData::from($verdict->bayesian)
                : null,
        );
    }

    /**
     * Accepts the plain PHP array that ExperimentResultsData::variantResults
     * carries and returns a Collection so the caller can chain ->toArray().
     *
     * @param  list<VariantResultData>  $variantResults
     * @return Collection<int, self>
     */
    public static function fromCollection(array $variantResults): Collection
    {
        return new Collection($variantResults)
            ->map(static fn (VariantResultData $v): self => self::from($v));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key'        => $this->key,
            'is_control' => $this->isControl,
            'primary_metric' => [
                'count_of_units'  => $this->countOfUnits,
                'conversions'     => $this->conversions,
                'conversion_rate' => $this->conversionRate,
                'mean'            => $this->mean,
            ],
            'verdict' => $this->verdictRecommendation !== null ? [
                'recommendation' => $this->verdictRecommendation,
                'label'          => $this->verdictLabel,
                'srm_detected'   => $this->verdictSrmDetected,
                'frequentist'    => $this->frequentistAnalysis?->toArray(),
                'bayesian'       => $this->bayesianAnalysis?->toArray(),
            ] : null,
        ];
    }
}
