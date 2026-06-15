<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Resources\Data;

use ABTests\Values\AnalysisResult;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Serialization DTO for a single AnalysisResult value object. Nested inside
 * VariantResultOutputData to represent the per-engine statistical output.
 */
final readonly class AnalysisResultData implements Arrayable
{
    /**
     * @param array{0: float, 1: float} $confidenceInterval
     */
    public function __construct(
        public float $relativeLift,
        public bool $isSignificant,
        public array $confidenceInterval,
        public ?float $pValue,
        public ?float $probabilityToBeatControl,
        public ?float $expectedLoss,
    ) {
        //
    }

    public static function from(AnalysisResult $result): self
    {
        return new self(
            relativeLift: $result->relativeLift,
            isSignificant: $result->isSignificant,
            confidenceInterval: $result->interval,
            pValue: $result->pValue,
            probabilityToBeatControl: $result->probabilityToBeatControl,
            expectedLoss: $result->expectedLoss,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'relative_lift'               => $this->relativeLift,
            'is_significant'              => $this->isSignificant,
            'confidence_interval'         => $this->confidenceInterval,
            'p_value'                     => $this->pValue,
            'probability_to_beat_control' => $this->probabilityToBeatControl,
            'expected_loss'               => $this->expectedLoss,
        ];
    }
}
