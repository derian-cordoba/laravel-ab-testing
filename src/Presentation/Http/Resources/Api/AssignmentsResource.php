<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Resources\Api;

use ABTests\Presentation\Http\Resources\Data\AssignmentsData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * Serializes one unit's sticky assignment map as a JSON:API resource.
 *
 * id   → "{unit_type}:{unit_key}"
 * type → assignments
 *
 * @mixin AssignmentsData
 *
 * @property-read AssignmentsData $resource
 */
final class AssignmentsResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return "{$this->resource->unitType}:{$this->resource->unitKey}";
    }

    public function toType(Request $request): string
    {
        return 'assignments';
    }

    /**
     * @return array{unit_type: string, unit_key: string, assignments: array<string, string>|object}
     */
    public function toAttributes(Request $request): array
    {
        $attributes = $this->resource->toArray();

        if ($attributes['assignments'] === []) {
            $attributes['assignments'] = (object) [];
        }

        return $attributes;
    }
}
