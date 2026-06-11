<?php

declare(strict_types=1);

namespace ABTests\Registry;

use ABTests\Definitions\FeatureFlagDefinition;
use ABTests\Exceptions\FeatureFlagNotFound;

/**
 * Runtime store for all known FeatureFlagDefinitions. Mirrors ExperimentRegistry
 * in structure: definitions may arrive from attribute-decorated PHP classes (via
 * AttributeReader) and are keyed by both their stable flag key and their class name.
 *
 * The registry is populated at boot time and is read-only during a request.
 */
final class FeatureFlagRegistry
{
    /** @var array<string, FeatureFlagDefinition> Keyed by flag key. */
    private array $definitions = [];

    /** @var array<string, string> Maps class-string → flag key. */
    private array $classToKey = [];

    /**
     * @param class-string|null $flagClass
     */
    public function register(FeatureFlagDefinition $definition, ?string $flagClass = null): void
    {
        $this->definitions[$definition->key] = $definition;

        if ($flagClass !== null) {
            $this->classToKey[$flagClass] = $definition->key;
        }
    }

    /**
     * @param class-string $flagClass
     *
     * @throws FeatureFlagNotFound
     */
    public function findByClass(string $flagClass): FeatureFlagDefinition
    {
        $key = $this->classToKey[$flagClass] ?? null;

        if ($key === null) {
            throw new FeatureFlagNotFound($flagClass);
        }

        return $this->definitions[$key];
    }

    /**
     * @throws FeatureFlagNotFound
     */
    public function findByKey(string $key): FeatureFlagDefinition
    {
        if (! isset($this->definitions[$key])) {
            throw new FeatureFlagNotFound($key);
        }

        return $this->definitions[$key];
    }

    /**
     * @return array<string, FeatureFlagDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    public function isEmpty(): bool
    {
        return $this->definitions === [];
    }
}
