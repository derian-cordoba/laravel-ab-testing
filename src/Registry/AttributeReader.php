<?php

declare(strict_types=1);

namespace ABTests\Registry;

use ABTests\Attributes\Analysis;
use ABTests\Attributes\AsExperiment;
use ABTests\Attributes\AsFeatureFlag;
use ABTests\Attributes\AsMetric;
use ABTests\Attributes\AsUnit;
use ABTests\Attributes\Guardrail;
use ABTests\Attributes\PrimaryMetric;
use ABTests\Attributes\SecondaryMetric;
use ABTests\Contracts\Variant;
use ABTests\Definitions\ExperimentDefinition;
use ABTests\Definitions\FeatureFlagDefinition;
use ABTests\Definitions\MetricBinding;
use ABTests\Enums\MetricRole;
use ABTests\Enums\MetricType;
use ABTests\Experiment;
use ABTests\FeatureFlag;
use ABTests\Values\Allocation;
use ABTests\Values\AnalysisConfiguration;
use ABTests\Values\Confidence;
use BackedEnum;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

/**
 * Reads the PHP attributes decorating an Experiment subclass and normalizes
 * them into a framework-agnostic ExperimentDefinition. This is one of the two
 * front-ends described in §4 of CLAUDE.md; the other is the database loader
 * (dashboard-created experiments) which builds the identical value object from
 * plain data.
 */
final readonly class AttributeReader
{
    /**
     * @param class-string<Experiment> $experimentClass
     *
     * @throws InvalidArgumentException|ReflectionException When a required attribute is missing or
     *                                   the variants enum is malformed.
     */
    public function readExperiment(string $experimentClass): ExperimentDefinition
    {
        $reflector = new ReflectionClass($experimentClass);

        $asExperiment = $this->readRequiredAttribute($reflector, AsExperiment::class, $experimentClass);

        $unitType = $this->resolveUnitType($asExperiment->unit);

        $variantsClass = $asExperiment->variants;

        if (! is_a($variantsClass, BackedEnum::class, true)) {
            throw new InvalidArgumentException(
                "Variants class [$variantsClass] must be a backed enum that implements Variant."
            );
        }

        $allocation = $this->buildAllocation($variantsClass);
        $metrics = $this->readMetricBindings($reflector);
        $analysisConfig = $this->readAnalysisConfiguration($reflector);

        $instance = new $experimentClass();
        $audience = $instance->audience();

        return new ExperimentDefinition(
            key: $asExperiment->key,
            unitType: $unitType,
            allocation: $allocation,
            analysis: $analysisConfig,
            audience: $audience,
            metrics: $metrics,
            name: $asExperiment->name,
            layer: $asExperiment->layer,
        );
    }

    /**
     * @param class-string<FeatureFlag> $flagClass
     *
     * @throws InvalidArgumentException|ReflectionException
     */
    public function readFeatureFlag(string $flagClass): FeatureFlagDefinition
    {
        $reflector = new ReflectionClass($flagClass);
        $attrs = $reflector->getAttributes(AsFeatureFlag::class);

        if ($attrs === []) {
            throw new InvalidArgumentException(
                "Class [$flagClass] is missing the required #[AsFeatureFlag] attribute."
            );
        }

        /** @var AsFeatureFlag $asFlag */
        $asFlag = $attrs[0]->newInstance();
        $unitType = $this->resolveUnitType($asFlag->unit);

        return new FeatureFlagDefinition(
            key: $asFlag->key,
            unitType: $unitType,
            defaultValue: $asFlag->defaultValue,
        );
    }

    /**
     * Build a map of metric key → MetricType for the given experiment class.
     * Used by the rollup job to decide which metrics need the delta-method
     * sufficient statistics. Returns an empty array for runtime-defined classes.
     *
     * @param class-string $experimentClass
     * @return array<string, MetricType>
     */
    public function readMetricTypes(string $experimentClass): array
    {
        $reflector = new ReflectionClass($experimentClass);
        $types = [];

        $metricAttributes = array_merge(
            $reflector->getAttributes(PrimaryMetric::class),
            $reflector->getAttributes(SecondaryMetric::class),
            $reflector->getAttributes(Guardrail::class),
        );

        foreach ($metricAttributes as $attr) {
            /** @var PrimaryMetric|SecondaryMetric|Guardrail $binding */
            $binding = $attr->newInstance();
            $metricClass = $binding->metric;

            if (! class_exists($metricClass)) {
                continue;
            }

            $metricReflector = new ReflectionClass($metricClass);
            $metricAttrs = $metricReflector->getAttributes(AsMetric::class);

            if ($metricAttrs === []) {
                continue;
            }

            /** @var AsMetric $asMetric */
            $asMetric = $metricAttrs[0]->newInstance();
            $types[$asMetric->key] = $asMetric->type;
        }

        return $types;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @template TAttr of object
     * @template TClass of object
     * @param ReflectionClass<TClass>  $reflector
     * @param class-string<TAttr>      $attributeClass
     * @return TAttr
     */
    private function readRequiredAttribute(
        ReflectionClass $reflector,
        string $attributeClass,
        string $experimentClass,
    ): object {
        $attrs = $reflector->getAttributes($attributeClass);

        if ($attrs === []) {
            throw new InvalidArgumentException(
                "Class [$experimentClass] is missing the required #[$attributeClass] attribute."
            );
        }

        return $attrs[0]->newInstance();
    }

    /**
     * Read the stable type key from the unit class's #[AsUnit] attribute.
     *
     * @param class-string $unitClass
     *
     * @throws ReflectionException
     */
    private function resolveUnitType(string $unitClass): string
    {
        $reflector = new ReflectionClass($unitClass);
        $attrs = $reflector->getAttributes(AsUnit::class);

        if ($attrs === []) {
            throw new InvalidArgumentException(
                "Unit class [$unitClass] is missing the #[AsUnit] attribute. " .
                "Add #[AsUnit(key: 'your-type-key')] to the class."
            );
        }

        return $attrs[0]->newInstance()->key;
    }

    /**
     * Instantiate each case of the variants enum (which must use the IsVariant
     * trait) and wrap them into a validated Allocation.
     *
     * @param class-string<BackedEnum&Variant> $variantsEnum
     */
    private function buildAllocation(string $variantsEnum): Allocation
    {
        /** @var list<BackedEnum&Variant> $cases */
        $cases = $variantsEnum::cases();

        return Allocation::fromVariants($cases);
    }

    /**
     * @template TClass of object
     * @param ReflectionClass<TClass> $reflector
     * @return list<MetricBinding>
     */
    private function readMetricBindings(ReflectionClass $reflector): array
    {
        $bindings = [];

        foreach ($reflector->getAttributes(PrimaryMetric::class) as $attr) {
            $primary = $attr->newInstance();
            $bindings[] = new MetricBinding($this->resolveMetricKey($primary->metric), MetricRole::primary);
        }

        foreach ($reflector->getAttributes(SecondaryMetric::class) as $attr) {
            $secondary = $attr->newInstance();
            $bindings[] = new MetricBinding($this->resolveMetricKey($secondary->metric), MetricRole::secondary);
        }

        foreach ($reflector->getAttributes(Guardrail::class) as $attr) {
            $guardrail = $attr->newInstance();
            $bindings[] = new MetricBinding(
                metric: $this->resolveMetricKey($guardrail->metric),
                role: MetricRole::guardrail,
                maximumRegression: $guardrail->maximumRegression,
            );
        }

        return $bindings;
    }

    /**
     * @template TClass of object
     * @param ReflectionClass<TClass> $reflector
     */
    private function readAnalysisConfiguration(ReflectionClass $reflector): AnalysisConfiguration
    {
        $attrs = $reflector->getAttributes(Analysis::class);

        if ($attrs === []) {
            return AnalysisConfiguration::default();
        }

        $analysis = $attrs[0]->newInstance();

        return new AnalysisConfiguration(
            engine: $analysis->engine,
            confidence: new Confidence($analysis->confidenceLevel),
            sequential: $analysis->sequential,
        );
    }

    /**
     * Resolve a metric class-string to its #[AsMetric] key. If the class has no
     * attribute, preserve the original string.
     */
    private function resolveMetricKey(string $metricClassOrKey): string
    {
        if (! class_exists($metricClassOrKey)) {
            return $metricClassOrKey;
        }

        $reflector = new ReflectionClass($metricClassOrKey);
        $attrs = $reflector->getAttributes(AsMetric::class);

        if ($attrs === []) {
            return $metricClassOrKey;
        }

        /** @var AsMetric $asMetric */
        $asMetric = $attrs[0]->newInstance();

        return $asMetric->key;
    }
}
