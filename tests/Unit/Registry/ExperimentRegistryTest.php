<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Registry;

use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Tests\Support\MakesDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExperimentRegistryTest extends TestCase
{
    use MakesDefinition;

    #[Test]
    public function is_empty_initially(): void
    {
        self::assertTrue(new ExperimentRegistry()->isEmpty());
    }

    #[Test]
    public function register_and_find_by_key(): void
    {
        $registry = new ExperimentRegistry();
        $definition = $this->makeDefinition(key: 'my-exp');

        $registry->register($definition);

        self::assertSame($definition, $registry->findByKey('my-exp'));
    }

    #[Test]
    public function register_and_find_by_class(): void
    {
        $registry = new ExperimentRegistry();
        $definition = $this->makeDefinition(key: 'my-exp');

        $registry->register($definition, 'App\\Experiments\\MyExperiment');

        self::assertSame($definition, $registry->findByClass('App\\Experiments\\MyExperiment'));
    }

    #[Test]
    public function find_by_key_throws_for_unknown_key(): void
    {
        $registry = new ExperimentRegistry();

        $this->expectException(ExperimentNotFound::class);
        $registry->findByKey('unknown');
    }

    #[Test]
    public function find_by_class_throws_for_unregistered_class(): void
    {
        $registry = new ExperimentRegistry();

        $this->expectException(ExperimentNotFound::class);
        $registry->findByClass('App\\Experiments\\Nonexistent');
    }

    #[Test]
    public function all_returns_all_registered_definitions(): void
    {
        $registry = new ExperimentRegistry();
        $a = $this->makeDefinition(key: 'exp-a');
        $b = $this->makeDefinition(key: 'exp-b');

        $registry->register($a);
        $registry->register($b);

        $all = $registry->all();
        self::assertArrayHasKey('exp-a', $all);
        self::assertArrayHasKey('exp-b', $all);
    }

    #[Test]
    public function later_registration_overwrites_same_key(): void
    {
        $registry = new ExperimentRegistry();
        $first  = $this->makeDefinition(key: 'exp');
        $second = $this->makeDefinition(key: 'exp', layer: 'checkout');

        $registry->register($first);
        $registry->register($second);

        self::assertSame('checkout', $registry->findByKey('exp')->layer);
    }
}
