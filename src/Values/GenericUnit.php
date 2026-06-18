<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Contracts\Bucketable;

/**
 * A bucketable subject built from a plain identifier and a bag of attributes,
 * for cases where writing a dedicated unit class is unnecessary, e.g. a guest
 * visitor keyed by a cookie id. Dedicated classes (UserUnit, TenantUnit) remain
 * the typed option; this is the general escape hatch.
 */
final readonly class GenericUnit implements Bucketable
{
    /**
     * @param  array<string, scalar|array<scalar>|null>  $attributes
     */
    public function __construct(
        private string $key,
        private array $attributes = [],
    ) {
        //
    }

    public function bucketingKey(): string
    {
        return $this->key;
    }

    public function attributes(): array
    {
        return $this->attributes;
    }
}
