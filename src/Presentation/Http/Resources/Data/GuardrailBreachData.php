<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Resources\Data;

use ABTests\Infrastructure\Database\Models\GuardrailBreachModel;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Serialization DTO for a single GuardrailBreachModel. Implements Arrayable so
 * that a Laravel Collection of these can be flattened with ->toArray() in
 * resource attributes without any additional mapping step.
 *
 * Usage in resources:
 *   GuardrailBreachData::fromCollection($data->activeGuardrailBreaches)->toArray()
 */
final readonly class GuardrailBreachData implements Arrayable
{
    public function __construct(
        public string $metricKey,
        public float $observed,
        public float $threshold,
        public ?string $occurredAt,
    ) {
        //
    }

    public static function from(GuardrailBreachModel $model): self
    {
        return new self(
            metricKey: $model->metric_key,
            observed: $model->observed_value,
            threshold: $model->threshold_value,
            occurredAt: $model->breached_at?->toIso8601String(),
        );
    }

    /**
     * @param  EloquentCollection<int, GuardrailBreachModel>  $breaches
     * @return Collection<int, self>
     */
    public static function fromCollection(EloquentCollection $breaches): Collection
    {
        return $breaches->map(static fn (GuardrailBreachModel $breach): self => self::from($breach));
    }

    /**
     * @return array{metric_key: string, observed: float, threshold: float, occurred_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'metric_key' => $this->metricKey,
            'observed' => $this->observed,
            'threshold' => $this->threshold,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
