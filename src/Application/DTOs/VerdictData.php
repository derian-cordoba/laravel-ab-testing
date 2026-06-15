<?php

declare(strict_types=1);

namespace ABTests\Application\DTOs;

use DateTimeImmutable;

/**
 * Immutable representation of a computed experiment verdict. Encapsulates all
 * three outcomes — no data, SRM invalidation, and a full statistical result —
 * behind named constructors so the controller stays a thin dispatcher.
 *
 * Serialization lives in VerdictResource; business decisions about which branch
 * to take live in the named constructors here.
 */
final readonly class VerdictData
{
    /**
     * @param list<array<string, mixed>> $variants Per-treatment-variant verdicts.
     */
    public function __construct(
        public string $experimentKey,
        public string $status,
        public bool $srmDetected,
        public string $overallRecommendation,
        public array $variants,
        public ?DateTimeImmutable $computedAt = null,
        public ?int $totalUnits = null,
        public ?int $activeGuardrailBreaches = null,
        public ?string $message = null,
    ) {
    }

    /**
     * No rollup data exists yet. The experiment may be running but has not
     * accumulated enough events to produce a meaningful result.
     */
    public static function noResults(string $experimentKey, string $status): self
    {
        return new self(
            experimentKey: $experimentKey,
            status: $status,
            srmDetected: false,
            overallRecommendation: 'inconclusive',
            variants: [],
            message: 'No results available yet.',
        );
    }

    /**
     * Builds the full verdict from a populated ExperimentResultsData, handling
     * both the SRM-invalidation path and the normal statistical-result path.
     *
     * SRM detected → inconclusive + message, no variant details.
     * Normal       → per-variant verdicts + derived overall recommendation.
     */
    public static function fromResults(string $experimentKey, ExperimentResultsData $results): self
    {
        if ($results->sampleRatioMismatch->detected) {
            return new self(
                experimentKey: $experimentKey,
                status: $results->model->status,
                srmDetected: true,
                overallRecommendation: 'inconclusive',
                variants: [],
                computedAt: $results->computedAt,
                totalUnits: $results->totalAssignedUnits(),
                activeGuardrailBreaches: $results->activeGuardrailBreaches->count(),
                message: 'Sample ratio mismatch detected. Results are invalid. Investigate before shipping.',
            );
        }

        $variantVerdicts = [];
        $overallRecommendation = 'inconclusive';

        foreach ($results->variantResults as $variantResult) {
            if ($variantResult->variant->isControl()) {
                continue;
            }

            $verdict = $variantResult->verdictResult;

            $variantVerdicts[] = [
                'key'                         => $variantResult->variant->key(),
                'recommendation'              => $verdict?->verdict->value ?? 'inconclusive',
                'label'                       => $verdict?->verdict->label() ?? 'Inconclusive',
                'relative_lift'               => $verdict?->frequentist?->relativeLift ?? $verdict?->bayesian?->relativeLift ?? null,
                'is_significant'              => $verdict?->frequentist?->isSignificant ?? $verdict?->bayesian?->isSignificant ?? false,
                'p_value'                     => $verdict?->frequentist?->pValue,
                'probability_to_beat_control' => $verdict?->bayesian?->probabilityToBeatControl,
                'expected_loss'               => $verdict?->bayesian?->expectedLoss,
                'count_of_units'              => $variantResult->primaryMetricSummary->countOfUnits,
                'conversion_rate'             => $variantResult->primaryMetricSummary->conversionRate,
            ];

            // "ship" wins over "inconclusive"; "do_not_ship" overrides everything.
            if ($verdict !== null) {
                if ($verdict->verdict->value === 'do_not_ship') {
                    $overallRecommendation = 'do_not_ship';
                } elseif ($verdict->verdict->value === 'ship' && $overallRecommendation === 'inconclusive') {
                    $overallRecommendation = 'ship';
                }
            }
        }

        return new self(
            experimentKey: $experimentKey,
            status: $results->model->status,
            srmDetected: false,
            overallRecommendation: $overallRecommendation,
            variants: $variantVerdicts,
            computedAt: $results->computedAt,
            totalUnits: $results->totalAssignedUnits(),
            activeGuardrailBreaches: $results->activeGuardrailBreaches->count(),
        );
    }
}
