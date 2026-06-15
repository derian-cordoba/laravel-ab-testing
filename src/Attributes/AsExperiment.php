<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use ABTests\Enums\Environment;
use Attribute;
use UnitEnum;
use ABTests\Contracts\Bucketable;
use ABTests\Contracts\Variant;

/**
 * Declares a class as an experiment definition and supplies its structural
 * configuration. Structure lives in code and is read-only at runtime; only
 * operational state (status, traffic) lives in the database.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsExperiment
{
    /** Stable, kebab-case identifier, normalized from a string or enum case. */
    public string $key;

    /**
     * @param string|UnitEnum           $key      Stable, kebab-case identifier. Accepts a
     *                                            backed enum case (returns its value) or a
     *                                            unit enum case (returns its name), following
     *                                            the same semantics as Laravel's enum_value().
     * @param class-string<Bucketable>  $unit     Which subject this experiment buckets on.
     * @param class-string<Variant>     $variants The backed enum defining the arms.
     * @param string|null               $name     Human label for the dashboard.
     * @param string|null               $layer    Mutual-exclusion namespace; units enter
     *                                            at most one running experiment per layer.
     */
    /**
     * @param list<value-of<Environment>>|null $environments Restrict this experiment
     *        to the given environments (e.g. ['production']). null means all environments.
     */
    public function __construct(
        string|UnitEnum $key,
        public string $unit,
        public string $variants,
        public ?string $name = null,
        public ?string $layer = null,
        public ?array $environments = null,
    ) {
        $this->key = enum_value($key);
    }
}
