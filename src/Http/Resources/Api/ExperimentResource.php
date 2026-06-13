<?php

declare(strict_types=1);

namespace ABTests\Http\Resources\Api;

use ABTests\Http\Resources\Data\VariantData;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * Serializes an ExperimentModel + its variants as a JSON:API resource.
 * The experiment's slug key is used as the JSON:API resource identifier.
 *
 * @mixin ExperimentModel
 * @property-read ExperimentModel $resource
 */
final class ExperimentResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return $this->resource->key;
    }

    public function toType(Request $request): string
    {
        return 'experiments';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'name'               => $this->resource->name,
            'version'            => $this->resource->version,
            'layer'              => $this->resource->layer,
            'status'             => $this->resource->status,
            'traffic_percentage' => $this->resource->traffic_percentage,
            'is_killed'          => $this->resource->is_killed,
            'killed_at'          => $this->resource->killed_at?->toIso8601String(),
            'started_at'         => $this->resource->started_at?->toIso8601String(),
            'stopped_at'         => $this->resource->stopped_at?->toIso8601String(),
            'created_at'         => $this->resource->created_at?->toIso8601String(),
            'updated_at'         => $this->resource->updated_at?->toIso8601String(),
            'variants'           => $this->resource->relationLoaded('variants')
                ? VariantData::fromCollection($this->resource->variants)->toArray()
                : [],
        ];
    }
}
