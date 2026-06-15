<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Registry;

use ABTests\Definitions\FeatureFlagDefinition;
use ABTests\FeatureFlag;
use ABTests\Application\Registry\AttributeReader;
use ABTests\Tests\Fixtures\FeatureFlagWithMissingUnitAttribute;
use ABTests\Tests\Fixtures\TestFeatureFlag;
use ABTests\Values\Context;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AttributeReaderFeatureFlagTest extends TestCase
{
    private AttributeReader $reader;

    protected function setUp(): void
    {
        $this->reader = new AttributeReader();
    }

    #[Test]
    public function reads_flag_key(): void
    {
        $definition = $this->reader->readFeatureFlag(TestFeatureFlag::class);

        self::assertSame('test-flag', $definition->key);
    }

    #[Test]
    public function reads_unit_type_from_as_unit_attribute_on_unit_class(): void
    {
        $definition = $this->reader->readFeatureFlag(TestFeatureFlag::class);

        self::assertSame('test-user', $definition->unitType);
    }

    #[Test]
    public function reads_false_default_value(): void
    {
        $definition = $this->reader->readFeatureFlag(TestFeatureFlag::class);

        self::assertFalse($definition->defaultValue);
    }

    #[Test]
    public function name_is_null_when_not_provided(): void
    {
        $definition = $this->reader->readFeatureFlag(TestFeatureFlag::class);

        self::assertNull($definition->name);
    }

    #[Test]
    public function throws_when_as_feature_flag_attribute_is_missing(): void
    {
        $bare = new class extends FeatureFlag {
            public function resolve(Context $context): mixed
            {
                return false;
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->reader->readFeatureFlag($bare::class);
    }

    #[Test]
    public function throws_when_unit_class_is_missing_as_unit_attribute(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/missing the #\[AsUnit\] attribute/');

        $this->reader->readFeatureFlag(FeatureFlagWithMissingUnitAttribute::class);
    }
}
