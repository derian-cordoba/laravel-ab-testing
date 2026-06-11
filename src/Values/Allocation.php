<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Contracts\Variant;
use ABTests\Exceptions\InvalidAllocation;

/**
 * The validated set of variants for an experiment. Enforces the two invariants
 * that keep assignment sound: weights sum to 100, and exactly one control.
 * Also maps a bucket position to the variant that owns that slice of traffic.
 */
final readonly class Allocation
{
    /**
     * @param list<Variant> $variants
     */
    private function __construct(public array $variants)
    {
    }

    /**
     * @param list<Variant> $variants
     */
    public static function fromVariants(array $variants): self
    {
        if ($variants === []) {
            throw new InvalidAllocation('An experiment must declare at least one variant.');
        }

        $totalWeight = array_sum(array_map(
            static fn (Variant $variant): int => $variant->weight(),
            $variants,
        ));

        if ($totalWeight !== 100) {
            throw new InvalidAllocation(
                "Variant weights must sum to 100, got $totalWeight."
            );
        }

        $controls = array_filter(
            $variants,
            static fn (Variant $variant): bool => $variant->isControl(),
        );

        if (count($controls) !== 1) {
            throw new InvalidAllocation(
                'An experiment must declare exactly one control variant.'
            );
        }

        return new self($variants);
    }

    /**
     * Resolve a bucket position in [0, 1) to the variant that owns it.
     */
    public function variantAt(float $position): Variant
    {
        $cursor = 0.0;

        foreach ($this->variants as $variant) {
            $cursor += $variant->weight() / 100;

            if ($position < $cursor) {
                return $variant;
            }
        }

        return $this->variants[count($this->variants) - 1];
    }

    /**
     * Find a variant by its storage key, or return null when the key is not
     * present in this allocation. Used by LoadExistingAssignmentStep to
     * rehydrate a sticky assignment back to the original typed Variant (the
     * actual enum case for code-defined experiments, so match() works).
     */
    public function findVariantByKey(string $key): ?Variant
    {
        return array_find(
            $this->variants,
            static fn(Variant $variant) => $variant->key() === $key,
        );
    }

    public function control(): Variant
    {
        foreach ($this->variants as $variant) {
            if ($variant->isControl()) {
                return $variant;
            }
        }

        throw new InvalidAllocation('No control variant present.');
    }
}
