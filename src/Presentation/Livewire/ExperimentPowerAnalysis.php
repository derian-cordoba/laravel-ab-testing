<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Enums\MetricType;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Application\Registry\AttributeReader;
use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Domain\Analysis\PowerAnalysis;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Throwable;

/**
 * Embedded Livewire component that renders an interactive power analysis
 * calculator for one experiment. Outputs the required sample size per variant
 * arm and the total sample size across all arms.
 *
 * When the computed sample size differs from the stored target_sample_size,
 * an "Update target" button lets the user persist the new figure.
 */
final class ExperimentPowerAnalysis extends Component
{
    public string $experimentKey = '';

    #[Validate('required|numeric|min:0.001|max:0.999')]
    public float $baselineRate = 0.10;

    #[Validate('required|numeric|min:0.001|max:1.0')]
    public float $minimumDetectableEffect = 0.05;

    #[Validate('required|boolean')]
    public bool $isRelativeEffect = true;

    #[Validate('required|numeric|min:0.5|max:0.999')]
    public float $confidenceLevel = 0.95;

    #[Validate('required|numeric|min:0.5|max:0.999')]
    public float $statisticalPower = 0.80;

    /** @var array<string, mixed>|null */
    public ?array $result = null;
    public string $flashMessage = '';
    public string $flashType = 'success';

    /** @var array<string, MetricType> */
    public array $metricTypes = [];
    public bool $isBinaryMetric = true;
    public float $standardDeviation = 1.0;

    public function mount(string $experimentKey): void
    {
        $this->experimentKey = $experimentKey;
        $this->loadMetricTypes();
    }

    #[On('experiment-updated')]
    public function refresh(): void
    {
        $this->loadMetricTypes();
    }

    public function calculate(): void
    {
        $this->validate();

        try {
            $numberOfVariants = $this->resolveVariantCount();

            $calculator = new PowerAnalysis(
                confidenceLevel: $this->confidenceLevel,
                power: $this->statisticalPower,
            );

            $powerResult = $this->isBinaryMetric
                ? $calculator->forBinaryMetric(
                    baselineRate: $this->baselineRate,
                    minimumDetectableEffect: $this->minimumDetectableEffect,
                    isRelativeEffect: $this->isRelativeEffect,
                    numberOfVariants: $numberOfVariants,
                )
                : $calculator->forContinuousMetric(
                    baselineMean: $this->baselineRate,
                    standardDeviation: $this->standardDeviation,
                    minimumDetectableEffect: $this->minimumDetectableEffect,
                    isRelativeEffect: $this->isRelativeEffect,
                    numberOfVariants: $numberOfVariants,
                );

            $this->result = $powerResult->toArray();

            $this->flashMessage = '';
        } catch (Throwable $e) {
            $this->flashMessage = 'Calculation error: ' . $e->getMessage();
            $this->flashType = 'error';
            $this->result = null;
        }
    }

    /**
     * Persist the computed sample size as the experiment's target_sample_size.
     */
    public function saveTargetSampleSize(): void
    {
        if ($this->result === null) {
            return;
        }

        try {
            ExperimentModel::query()
                ->where('key', $this->experimentKey)
                ->update(['target_sample_size' => $this->result['totalSampleSize']]);

            $this->flashMessage = "Target sample size set to {$this->result['totalSampleSize']}.";
            $this->flashType = 'success';
            $this->dispatch('experiment-updated');
        } catch (Throwable $e) {
            $this->flashMessage = 'Failed to save: ' . $e->getMessage();
            $this->flashType = 'error';
        }
    }

    public function render(): View
    {
        $model = ExperimentModel::query()->firstWhere('key', $this->experimentKey);
        $currentTargetSampleSize = $model?->target_sample_size;

        return view('ab-testing::livewire.experiment-power-analysis', compact(
            'model',
            'currentTargetSampleSize',
        ));
    }

    private function loadMetricTypes(): void
    {
        try {
            $registry = app(ExperimentRegistry::class);
            $experimentClass = $registry->findClassByKey($this->experimentKey);

            if ($experimentClass === null) {
                return;
            }

            $this->metricTypes = app(AttributeReader::class)->readMetricTypes($experimentClass);

            // Default to binary if the primary metric is binary.
            $this->isBinaryMetric = $this->metricTypes[0] === MetricType::binary;
        } catch (Throwable) {
            //
        }
    }

    private function resolveVariantCount(): int
    {
        try {
            $definition = app(ExperimentRegistry::class)->findByKey($this->experimentKey);

            return count($definition->allocation->variants);
        } catch (Throwable) {
            return 2; // Default to A/B (control + one treatment).
        }
    }
}
