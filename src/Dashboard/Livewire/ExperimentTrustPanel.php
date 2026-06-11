<?php

declare(strict_types=1);

namespace ABTests\Dashboard\Livewire;

use ABTests\Application\ResultsService;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\VariantModel;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Renders the Trust panel for one experiment: per-variant observed vs expected
 * allocation, the chi-square statistic, p-value, and a clear SRM pass/fail
 * verdict. Reads from ResultsService (rollup-backed) so it never hits raw events
 * in the request path.
 */
final class ExperimentTrustPanel extends Component
{
    public string $experimentKey = '';

    public function mount(string $experimentKey): void
    {
        $this->experimentKey = $experimentKey;
    }

    #[On('experiment-updated')]
    public function refresh(): void
    {
        // Re-renders automatically.
    }

    public function render(): View
    {
        $results = app(ResultsService::class)->forExperiment($this->experimentKey);

        $rows        = [];
        $totalUnits  = 0;
        $srmResult   = null;

        if ($results !== null) {
            $srmResult = $results->sampleRatioMismatch;

            // Fetch intended weights from the variants snapshot (or fall back to
            // the allocation definition when no DB snapshot exists yet).
            $model = ExperimentModel::query()->firstWhere('key', $this->experimentKey);

            $intendedWeights = [];

            if ($model !== null) {
                $dbVariants = VariantModel::query()
                    ->where('experiment_id', $model->id)
                    ->get()
                    ->keyBy('key');

                foreach ($results->definition->allocation->variants as $variant) {
                    $dbWeight = $dbVariants->get($variant->key())?->weight;
                    $intendedWeights[$variant->key()] = $dbWeight ?? $variant->weight();
                }
            } else {
                foreach ($results->definition->allocation->variants as $variant) {
                    $intendedWeights[$variant->key()] = $variant->weight();
                }
            }

            // Build per-variant rows using countOfUnits from the primary metric summary.
            foreach ($results->variantResults as $variantResult) {
                $key      = $variantResult->variant->key();
                $observed = $variantResult->primaryMetricSummary->countOfUnits;
                $totalUnits += $observed;
                $rows[$key] = ['observed' => $observed, 'intended_weight' => $intendedWeights[$key] ?? 0];
            }

            // Compute expected counts and observed % once we have the total.
            foreach ($rows as $key => &$row) {
                $row['expected']          = $totalUnits > 0
                    ? round($totalUnits * ($row['intended_weight'] / 100))
                    : 0;
                $row['observed_percent']  = $totalUnits > 0
                    ? round($row['observed'] / $totalUnits * 100, 1)
                    : 0.0;
            }
            unset($row);
        }

        return view('ab-testing::livewire.experiment-trust-panel', compact(
            'rows',
            'totalUnits',
            'srmResult',
        ));
    }
}
