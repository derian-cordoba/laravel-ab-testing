<?php

declare(strict_types=1);

namespace ABTests\Http\Resources\Api;

use ABTests\Application\Data\ExperimentResultsData;
use ABTests\Http\Resources\Data\GuardrailBreachData;
use ABTests\Http\Resources\Data\VariantResultOutputData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * Serializes ExperimentResultsData as a JSON:API resource. The experiment key
 * is used as the JSON:API resource identifier.
 *
 * @mixin ExperimentResultsData
 */
final class ExperimentResultsResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        /** @var ExperimentResultsData $data */
        $data = $this->resource;

        return $data->model->key;
    }

    public function toType(Request $request): string
    {
        return 'experiment-results';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        /** @var ExperimentResultsData $data */
        $data = $this->resource;

        return [
            'status'      => $data->model->status,
            'computed_at' => $data->computedAt->format('c'),
            'total_units' => $data->totalAssignedUnits(),
            'srm'         => [
                'detected'   => $data->sampleRatioMismatch->detected,
                'chi_square' => $data->sampleRatioMismatch->chiSquare,
                'p_value'    => $data->sampleRatioMismatch->pValue,
            ],
            'active_guardrail_breaches' => GuardrailBreachData::fromCollection($data->activeGuardrailBreaches)->toArray(),
            'variants'                  => VariantResultOutputData::fromCollection($data->variantResults)->toArray(),
        ];
    }
}
