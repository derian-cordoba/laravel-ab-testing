<?php

declare(strict_types=1);

namespace ABTests\Http\Resources\Api;

use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * Serializes a FeatureFlagStateModel as a JSON:API resource.
 * The flag's slug key is used as the JSON:API resource identifier.
 *
 * @mixin FeatureFlagStateModel
 * @property-read FeatureFlagStateModel $resource
 */
final class FeatureFlagResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return $this->resource->key;
    }

    public function toType(Request $request): string
    {
        return 'feature-flags';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'is_enabled'          => $this->resource->is_enabled,
            'rollout_percentage'  => $this->resource->rollout_percentage,
            'conditions'          => $this->resource->conditions ?? [],
            'conditions_logic'    => $this->resource->conditions_logic?->value,
            'is_killed'           => $this->resource->killed_at !== null,
            'killed_at'           => $this->resource->killed_at?->toIso8601String(),
            'last_evaluated_at'   => $this->resource->last_evaluated_at?->toIso8601String(),
            'created_at'          => $this->resource->created_at?->toIso8601String(),
            'updated_at'          => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
