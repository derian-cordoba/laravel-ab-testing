<?php

declare(strict_types=1);

namespace ABTests\Console;

use ABTests\Registry\AttributeReader;
use ABTests\Registry\ExperimentRegistry;
use ABTests\Statistics\PowerAnalysis;
use Illuminate\Console\Command;
use Throwable;

/**
 * php artisan ab:power-analysis {experiment} [options]
 *
 * Runs a power analysis for the primary metric of the given experiment and
 * prints the required sample size per variant arm and in total.
 *
 * Usage:
 *   php artisan ab:power-analysis checkout-button-color --baseline=0.12 --mde=0.05
 */
final class PowerAnalysisCommand extends Command
{
    protected $signature = 'ab:power-analysis
        {experiment : The kebab-case experiment key}
        {--baseline=0.10      : Baseline conversion rate or mean}
        {--mde=0.05           : Minimum detectable effect (relative by default)}
        {--absolute           : Treat --mde as an absolute difference, not a percentage of baseline}
        {--confidence=0.95    : Confidence level (1 - α)}
        {--power=0.80         : Target statistical power (1 - β)}
        {--stddev=            : Standard deviation (required for continuous metrics)}';

    protected $description = 'Compute the required sample size for an experiment.';

    public function handle(ExperimentRegistry $registry, AttributeReader $reader): int
    {
        $experimentKey = (string) $this->argument('experiment');
        $baseline      = (float) $this->option('baseline');
        $mde           = (float) $this->option('mde');
        $isAbsolute    = (bool) $this->option('absolute');
        $confidence    = (float) $this->option('confidence');
        $power         = (float) $this->option('power');
        $stddev        = $this->option('stddev') !== null ? (float) $this->option('stddev') : null;

        $numberOfVariants = 2;
        $isBinary = true;

        try {
            $definition = $registry->findByKey($experimentKey);
            $numberOfVariants = count($definition->allocation->variants);

            $experimentClass = $registry->findClassByKey($experimentKey);

            if ($experimentClass !== null) {
                $metricTypes = $reader->readMetricTypes($experimentClass);

                $isBinary = $metricTypes[0]?->value === 'binary';
            }
        } catch (Throwable $e) {
            $this->warn("Could not load experiment definition: {$e->getMessage()}. Using defaults.");
        }

        try {
            $calculator = new PowerAnalysis(confidenceLevel: $confidence, power: $power);

            if ($isBinary || $stddev === null) {
                $result = $calculator->forBinaryMetric(
                    baselineRate: $baseline,
                    minimumDetectableEffect: $mde,
                    isRelativeEffect: ! $isAbsolute,
                    numberOfVariants: $numberOfVariants,
                );
            } else {
                $result = $calculator->forContinuousMetric(
                    baselineMean: $baseline,
                    standardDeviation: $stddev,
                    minimumDetectableEffect: $mde,
                    isRelativeEffect: ! $isAbsolute,
                    numberOfVariants: $numberOfVariants,
                );
            }
        } catch (Throwable $e) {
            $this->error('Power analysis failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Power Analysis — {$experimentKey}");
        $this->newLine();

        $this->table(
            ['Parameter', 'Value'],
            [
                ['Baseline rate / mean',    number_format($result->baselineRate * 100, 2) . '%'],
                ['Min detectable effect',   $result->isRelativeEffect
                    ? number_format($result->minimumDetectableEffect * 100, 1) . '% relative'
                    : number_format($result->minimumDetectableEffect, 4) . ' absolute'],
                ['Confidence level',        number_format($result->confidenceLevel * 100) . '%'],
                ['Statistical power',       number_format($result->power * 100) . '%'],
                ['Number of arms',          $result->numberOfVariants],
                ['Sample size per variant', number_format($result->sampleSizePerVariant)],
                ['Total sample size',       number_format($result->totalSampleSize)],
            ],
        );

        $this->newLine();

        return self::SUCCESS;
    }
}
