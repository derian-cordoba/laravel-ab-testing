<?php

declare(strict_types=1);

namespace ABTests\Application\Registry;

use ABTests\Definitions\ExperimentDefinition;
use ABTests\Exceptions\ExperimentNotFound;

/**
 * Runtime store for all known ExperimentDefinitions. Definitions may arrive
 * from two sources — attribute-decorated PHP classes (via AttributeReader) or
 * database rows (dashboard-created) — and are registered here under both their
 * experiment key and, for code-defined experiments, their class name.
 *
 * The registry is populated at boot time (from cache or by scanning) and is
 * read-only after that; it is never written to during a request.
 */
final class ExperimentRegistry
{
    /** @var array<string, ExperimentDefinition> Keyed by experiment key. */
    private array $definitions = [];

    /** @var array<string, string> Maps class-string → experiment key. */
    private array $classToKey = [];

    /** @var array<string, string> Maps experiment key → class-string. */
    private array $keyToClass = [];

    /**
     * Register a definition. For code-defined experiments, pass the decorated
     * class name as $experimentClass so it can also be looked up that way.
     *
     * @param class-string|null $experimentClass
     */
    public function register(ExperimentDefinition $definition, ?string $experimentClass = null): void
    {
        $this->definitions[$definition->key] = $definition;

        if ($experimentClass !== null) {
            $this->classToKey[$experimentClass] = $definition->key;
            $this->keyToClass[$definition->key]  = $experimentClass;
        }
    }

    /**
     * Look up a definition by the decorated PHP class name.
     *
     * @param class-string $experimentClass
     *
     * @throws ExperimentNotFound
     */
    public function findByClass(string $experimentClass): ExperimentDefinition
    {
        $key = $this->classToKey[$experimentClass] ?? null;

        if ($key === null) {
            throw new ExperimentNotFound($experimentClass);
        }

        return $this->definitions[$key];
    }

    /**
     * Look up a definition by its stable kebab-case key.
     *
     * @throws ExperimentNotFound
     */
    public function findByKey(string $key): ExperimentDefinition
    {
        if (! isset($this->definitions[$key])) {
            throw new ExperimentNotFound($key);
        }

        return $this->definitions[$key];
    }

    /**
     * Return the experiment class registered for the given key, or null when
     * the experiment was registered without a class (e.g. runtime-defined).
     *
     * @return class-string|null
     */
    public function findClassByKey(string $key): ?string
    {
        return $this->keyToClass[$key] ?? null;
    }

    /**
     * @return array<string, ExperimentDefinition>
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
