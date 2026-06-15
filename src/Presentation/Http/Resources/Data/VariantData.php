<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Resources\Data;

use ABTests\Infrastructure\Database\Models\VariantModel;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Serialization DTO for a single VariantModel. Implements Arrayable so that
 * a Laravel Collection of these can be flattened with ->toArray() in resource
 * attributes without any additional mapping step.
 *
 * Usage in resources:
 *   VariantData::fromCollection($this->resource->variants)->toArray()
 */
final readonly class VariantData implements Arrayable
{
    public function __construct(
        public int $id,
        public string $key,
        public int $weight,
        public bool $isControl,
    ) {
        //
    }

    public static function from(VariantModel $model): self
    {
        return new self(
            id: $model->id,
            key: $model->key,
            weight: $model->weight,
            isControl: $model->is_control,
        );
    }

    /**
     * @param  EloquentCollection<int, VariantModel>  $variants
     * @return Collection<int, self>
     */
    public static function fromCollection(EloquentCollection $variants): Collection
    {
        return $variants->map(static fn (VariantModel $variant): self => self::from($variant));
    }

    /**
     * @return array{id: int, key: string, weight: int, is_control: bool}
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'key'        => $this->key,
            'weight'     => $this->weight,
            'is_control' => $this->isControl,
        ];
    }
}
