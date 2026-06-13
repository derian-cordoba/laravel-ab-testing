<?php

declare(strict_types=1);

namespace ABTests\Http\Resources\Api;

use ABTests\Application\Data\VerdictData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * Serializes a VerdictData as a JSON:API resource. Covers all three verdict
 * outcomes (no data, SRM invalidation, full result) through the same structure
 * so callers always parse the same shape.
 *
 * id   → experiment key
 * type → experiment-verdicts
 *
 * @mixin VerdictData
 * @property-read VerdictData $resource
 */
final class VerdictResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return $this->resource->experimentKey;
    }

    public function toType(Request $request): string
    {
        return 'experiment-verdicts';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $attributes = [
            'status'                 => $this->resource->status,
            'srm_detected'           => $this->resource->srmDetected,
            'overall_recommendation' => $this->resource->overallRecommendation,
            'variants'               => $this->resource->variants,
        ];

        if ($this->resource->message !== null) {
            $attributes['message'] = $this->resource->message;
        }

        if ($this->resource->computedAt !== null) {
            $attributes['computed_at']               = $this->resource->computedAt->format('c');
            $attributes['total_units']               = $this->resource->totalUnits;
            $attributes['active_guardrail_breaches'] = $this->resource->activeGuardrailBreaches;
        }

        return $attributes;
    }
}
