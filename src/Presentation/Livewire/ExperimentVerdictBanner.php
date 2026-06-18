<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Application\ResultsService;
use ABTests\Enums\Verdict;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Headline verdict banner for the experiment detail page. Shows the overall
 * ship / do-not-ship / inconclusive decision derived from all treatment arms,
 * or a "collecting data" state when rollups are not yet available. An SRM
 * detection overrides the verdict display with a trust warning.
 */
final class ExperimentVerdictBanner extends Component
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

        $overallVerdict = null;
        $srmDetected = false;
        $hasData = false;
        $totalUnits = 0;
        $treatmentCount = 0;

        if ($results !== null && $results->hasResults()) {
            $hasData = true;
            $totalUnits = $results->totalAssignedUnits();
            $srmDetected = $results->sampleRatioMismatch->detected;

            // Derive overall verdict: doNotShip > ship > inconclusive.
            foreach ($results->variantResults as $variantResult) {
                if ($variantResult->variant->isControl()) {
                    continue;
                }

                $treatmentCount++;
                $verdict = $variantResult->verdictResult?->verdict;

                if ($verdict === null) {
                    continue;
                }

                if ($verdict === Verdict::doNotShip) {
                    $overallVerdict = Verdict::doNotShip;
                    break;
                }

                if ($verdict === Verdict::ship && $overallVerdict !== Verdict::doNotShip) {
                    $overallVerdict = Verdict::ship;
                }

                if ($verdict === Verdict::inconclusive && $overallVerdict === null) {
                    $overallVerdict = Verdict::inconclusive;
                }
            }
        }

        return view('ab-testing::livewire.experiment-verdict-banner', compact(
            'overallVerdict',
            'srmDetected',
            'hasData',
            'totalUnits',
            'treatmentCount',
        ));
    }
}
