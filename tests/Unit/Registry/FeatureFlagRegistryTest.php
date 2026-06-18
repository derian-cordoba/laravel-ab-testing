<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Registry;

use ABTests\Application\Registry\FeatureFlagRegistry;
use ABTests\Definitions\FeatureFlagDefinition;
use ABTests\Exceptions\FeatureFlagNotFound;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FeatureFlagRegistryTest extends TestCase
{
    #[Test]
    public function is_empty_initially(): void
    {
        self::assertTrue((new FeatureFlagRegistry())->isEmpty());
    }

    #[Test]
    public function not_empty_after_registering_a_flag(): void
    {
        $registry = new FeatureFlagRegistry();
        $registry->register($this->makeDefinition('my-flag'));

        self::assertFalse($registry->isEmpty());
    }

    #[Test]
    public function register_and_find_by_key(): void
    {
        $registry = new FeatureFlagRegistry();
        $definition = $this->makeDefinition('checkout-express-payment');

        $registry->register($definition);

        self::assertSame($definition, $registry->findByKey('checkout-express-payment'));
    }

    #[Test]
    public function register_and_find_by_class(): void
    {
        $registry = new FeatureFlagRegistry();
        $definition = $this->makeDefinition('new-dashboard');

        $registry->register($definition, 'App\\Flags\\NewDashboardFlag');

        self::assertSame($definition, $registry->findByClass('App\\Flags\\NewDashboardFlag'));
    }

    #[Test]
    public function find_by_key_throws_for_unknown_key(): void
    {
        $registry = new FeatureFlagRegistry();

        $this->expectException(FeatureFlagNotFound::class);
        $registry->findByKey('no-such-flag');
    }

    #[Test]
    public function find_by_class_throws_for_unregistered_class(): void
    {
        $registry = new FeatureFlagRegistry();

        $this->expectException(FeatureFlagNotFound::class);
        $registry->findByClass('App\\Flags\\Nonexistent');
    }

    #[Test]
    public function find_by_class_throws_when_registered_without_class(): void
    {
        $registry = new FeatureFlagRegistry();
        $registry->register($this->makeDefinition('my-flag'));  // no class passed

        $this->expectException(FeatureFlagNotFound::class);
        $registry->findByClass('App\\Flags\\MyFlag');
    }

    #[Test]
    public function all_returns_all_registered_definitions_keyed_by_flag_key(): void
    {
        $registry = new FeatureFlagRegistry();
        $a = $this->makeDefinition('flag-a');
        $b = $this->makeDefinition('flag-b');

        $registry->register($a);
        $registry->register($b);

        $all = $registry->all();
        self::assertArrayHasKey('flag-a', $all);
        self::assertArrayHasKey('flag-b', $all);
        self::assertSame($a, $all['flag-a']);
        self::assertSame($b, $all['flag-b']);
    }

    #[Test]
    public function later_registration_overwrites_same_key(): void
    {
        $registry = new FeatureFlagRegistry();
        $first = $this->makeDefinition('my-flag', defaultValue: false);
        $second = $this->makeDefinition('my-flag', defaultValue: true);

        $registry->register($first);
        $registry->register($second);

        self::assertTrue($registry->findByKey('my-flag')->defaultValue);
    }

    #[Test]
    public function later_class_registration_updates_the_class_to_key_mapping(): void
    {
        $registry = new FeatureFlagRegistry();
        $definition = $this->makeDefinition('my-flag');

        $registry->register($definition, 'App\\Flags\\OldName');
        $registry->register($definition, 'App\\Flags\\NewName');

        self::assertSame($definition, $registry->findByClass('App\\Flags\\NewName'));
    }

    #[Test]
    public function stores_default_value_on_definition(): void
    {
        $registry = new FeatureFlagRegistry();
        $definition = $this->makeDefinition('priced-feature', defaultValue: 'disabled');

        $registry->register($definition);

        self::assertSame('disabled', $registry->findByKey('priced-feature')->defaultValue);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeDefinition(string $key, mixed $defaultValue = false): FeatureFlagDefinition
    {
        return new FeatureFlagDefinition(
            key: $key,
            unitType: 'user',
            defaultValue: $defaultValue,
            name: null,
        );
    }
}
