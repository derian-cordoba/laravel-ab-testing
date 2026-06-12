<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Values;

use ABTests\Values\PowerAnalysisResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PowerAnalysisResultTest extends TestCase
{
    private function makeResult(array $overrides = []): PowerAnalysisResult
    {
        return new PowerAnalysisResult(
            sampleSizePerVariant:    $overrides['sampleSizePerVariant']    ?? 1000,
            numberOfVariants:        $overrides['numberOfVariants']        ?? 2,
            baselineRate:            $overrides['baselineRate']            ?? 0.10,
            minimumDetectableEffect: $overrides['minimumDetectableEffect'] ?? 0.05,
            isRelativeEffect:        $overrides['isRelativeEffect']        ?? true,
            confidenceLevel:         $overrides['confidenceLevel']         ?? 0.95,
            power:                   $overrides['power']                   ?? 0.80,
            totalSampleSize:         $overrides['totalSampleSize']         ?? 2000,
        );
    }

    #[Test]
    public function to_array_contains_all_eight_keys(): void
    {
        $result = $this->makeResult();

        $array = $result->toArray();

        self::assertArrayHasKey('sampleSizePerVariant',    $array);
        self::assertArrayHasKey('numberOfVariants',        $array);
        self::assertArrayHasKey('baselineRate',            $array);
        self::assertArrayHasKey('minimumDetectableEffect', $array);
        self::assertArrayHasKey('isRelativeEffect',        $array);
        self::assertArrayHasKey('confidenceLevel',         $array);
        self::assertArrayHasKey('power',                   $array);
        self::assertArrayHasKey('totalSampleSize',         $array);
    }

    #[Test]
    public function to_array_values_match_constructor_arguments(): void
    {
        $result = $this->makeResult([
            'sampleSizePerVariant'    => 57764,
            'numberOfVariants'        => 2,
            'baselineRate'            => 0.10,
            'minimumDetectableEffect' => 0.05,
            'isRelativeEffect'        => true,
            'confidenceLevel'         => 0.95,
            'power'                   => 0.80,
            'totalSampleSize'         => 115528,
        ]);

        self::assertSame([
            'sampleSizePerVariant'    => 57764,
            'numberOfVariants'        => 2,
            'baselineRate'            => 0.10,
            'minimumDetectableEffect' => 0.05,
            'isRelativeEffect'        => true,
            'confidenceLevel'         => 0.95,
            'power'                   => 0.80,
            'totalSampleSize'         => 115528,
        ], $result->toArray());
    }

    #[Test]
    public function to_array_preserves_integer_types_for_counts(): void
    {
        $result = $this->makeResult([
            'sampleSizePerVariant' => 500,
            'numberOfVariants'     => 3,
            'totalSampleSize'      => 1500,
        ]);

        $array = $result->toArray();

        self::assertIsInt($array['sampleSizePerVariant']);
        self::assertIsInt($array['numberOfVariants']);
        self::assertIsInt($array['totalSampleSize']);
    }

    #[Test]
    public function to_array_preserves_float_types_for_rates(): void
    {
        $result = $this->makeResult([
            'baselineRate'            => 0.123,
            'minimumDetectableEffect' => 0.05,
            'confidenceLevel'         => 0.99,
            'power'                   => 0.90,
        ]);

        $array = $result->toArray();

        self::assertIsFloat($array['baselineRate']);
        self::assertIsFloat($array['minimumDetectableEffect']);
        self::assertIsFloat($array['confidenceLevel']);
        self::assertIsFloat($array['power']);
    }

    #[Test]
    public function to_array_preserves_boolean_for_relative_effect_flag(): void
    {
        self::assertTrue($this->makeResult(['isRelativeEffect' => true])->toArray()['isRelativeEffect']);
        self::assertFalse($this->makeResult(['isRelativeEffect' => false])->toArray()['isRelativeEffect']);
    }

    #[Test]
    public function total_sample_size_equals_per_variant_times_number_of_variants(): void
    {
        $result = $this->makeResult([
            'sampleSizePerVariant' => 300,
            'numberOfVariants'     => 4,
            'totalSampleSize'      => 1200,
        ]);

        $array = $result->toArray();

        self::assertSame(
            $array['sampleSizePerVariant'] * $array['numberOfVariants'],
            $array['totalSampleSize'],
        );
    }
}
