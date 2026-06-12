<?php

declare(strict_types=1);

namespace ABTests\Values;

/**
 * The result of a power analysis for one experiment metric.
 *
 * Carries the recommended sample size per variant arm alongside the input
 * assumptions so callers can surface all of them in a dashboard or report.
 */
final readonly class PowerAnalysisResult
{
    /**
     * @param int   $sampleSizePerVariant    Units required in each arm to achieve the target power.
     * @param int   $numberOfVariants        Number of arms (including control).
     * @param float $baselineRate            Observed or assumed baseline rate / mean.
     * @param float $minimumDetectableEffect Smallest absolute or relative effect worth detecting.
     * @param bool  $isRelativeEffect        True when $minimumDetectableEffect is relative (e.g. 0.05 = 5%).
     * @param float $confidenceLevel         1 - α (e.g. 0.95).
     * @param float $power                   1 - β (e.g. 0.80).
     * @param int   $totalSampleSize         sampleSizePerVariant × numberOfVariants.
     */
    public function __construct(
        public int $sampleSizePerVariant,
        public int $numberOfVariants,
        public float $baselineRate,
        public float $minimumDetectableEffect,
        public bool $isRelativeEffect,
        public float $confidenceLevel,
        public float $power,
        public int $totalSampleSize,
    ) {
        //
    }

    /**
     * @return array{sampleSizePerVariant: int, numberOfVariants: int, baselineRate: float, minimumDetectableEffect: float, isRelativeEffect: bool, confidenceLevel: float, power: float, totalSampleSize: int}
     */
    public function toArray(): array
    {
        return [
            'sampleSizePerVariant'    => $this->sampleSizePerVariant,
            'numberOfVariants'        => $this->numberOfVariants,
            'baselineRate'            => $this->baselineRate,
            'minimumDetectableEffect' => $this->minimumDetectableEffect,
            'isRelativeEffect'        => $this->isRelativeEffect,
            'confidenceLevel'         => $this->confidenceLevel,
            'power'                   => $this->power,
            'totalSampleSize'         => $this->totalSampleSize,
        ];
    }
}
