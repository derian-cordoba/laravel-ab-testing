<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Application\ResultsService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Livewire subcomponent that owns the results table, SRM warning, and
 * guardrail breach panel for one experiment. Reacts to the experiment-updated
 * event so it refreshes after any lifecycle command without a full page reload.
 */
final class ExperimentResultsTable extends Component
{
    public string $experimentKey = '';

    public function mount(string $experimentKey): void
    {
        $this->experimentKey = $experimentKey;
    }

    #[On('experiment-updated')]
    public function refresh(): void
    {
        // Re-render triggered automatically.
    }

    public function render(): View
    {
        $results = app(ResultsService::class)->forExperiment($this->experimentKey);

        return view('ab-testing::livewire.experiment-results-table', compact('results'));
    }
}
