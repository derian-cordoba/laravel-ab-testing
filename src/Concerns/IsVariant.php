<?php

declare(strict_types=1);

namespace ABTests\Concerns;

use ABTests\Attributes\Control;
use ABTests\Attributes\Weight;
use ABTests\Contracts\Variant;
use ABTests\Exceptions\MissingVariantWeight;
use BackedEnum;
use ReflectionEnumBackedCase;

/**
 * Implements the Variant contract for a backed enum by reading the #[Weight]
 * and #[Control] attributes off each case. Keeps the enum the single, fully
 * type-safe declaration of an experiment's arms.
 *
 * @mixin BackedEnum
 *
 * @phpstan-require-implements Variant
 */
trait IsVariant
{
    public function key(): string
    {
        return $this->value;
    }

    public function weight(): int
    {
        $weight = $this->caseAttribute(Weight::class);

        if (! $weight instanceof Weight) {
            throw new MissingVariantWeight(static::class, $this->name);
        }

        return $weight->percentage;
    }

    public function isControl(): bool
    {
        return $this->caseAttribute(Control::class) !== null;
    }

    private function caseAttribute(string $attribute): ?object
    {
        $reflection = new ReflectionEnumBackedCase(static::class, $this->name);
        $attributes = $reflection->getAttributes($attribute);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
