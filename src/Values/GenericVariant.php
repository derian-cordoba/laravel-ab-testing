<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Contracts\Variant;

/**
 * A variant defined at runtime rather than as an enum case. This is what makes
 * the package general: experiments created in the dashboard (and stored in the
 * database) produce GenericVariant instances, while code-defined experiments
 * use a backed enum with the IsVariant trait. Both satisfy the Variant contract
 * and flow through the exact same Allocation and resolver.
 */
final readonly class GenericVariant implements Variant
{
    public function __construct(
        private string $key,
        private int $weight,
        private bool $isControl = false,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function weight(): int
    {
        return $this->weight;
    }

    public function isControl(): bool
    {
        return $this->isControl;
    }
}
