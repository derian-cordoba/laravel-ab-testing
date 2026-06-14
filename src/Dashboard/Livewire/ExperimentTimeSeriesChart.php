<?php

declare(strict_types=1);

namespace ABTests\Dashboard\Livewire;

use ABTests\Infrastructure\Database\Models\EventModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Renders a cumulative conversion-rate line chart per variant over the
 * experiment's lifetime. Reads directly from the events table (acceptable for
 * dashboard use — this is not a hot application path). Hidden when fewer than
 * two days of data are available.
 */
final class ExperimentTimeSeriesChart extends Component
{
    public string $experimentKey = '';

    /** Set by refresh() so render() knows to dispatch the post-morph signal. */
    private bool $shouldDispatchRefresh = false;

    public function mount(string $experimentKey): void
    {
        $this->experimentKey = $experimentKey;
    }

    #[On('experiment-updated')]
    public function refresh(): void
    {
        // Mark that render() should dispatch 'chart-data-refreshed' after the
        // next morph. Doing the dispatch here would require a second buildSeries()
        // call, since render() runs immediately after this method returns.
        $this->shouldDispatchRefresh = true;
    }

    public function render(): View
    {
        ['series' => $series, 'dates' => $dates] = $this->buildSeries();

        if ($this->shouldDispatchRefresh) {
            // Dispatched after the DOM morph commits, so Alpine can safely read
            // the updated carrier element when it receives the browser event.
            $this->dispatch('chart-data-refreshed', hasData: !empty($series) && count($dates) >= 2);
            $this->shouldDispatchRefresh = false;
        }

        return view('ab-testing::livewire.experiment-time-series-chart', compact('series', 'dates'));
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Returns per-variant cumulative conversion-rate series and the sorted date
     * labels. Each series entry:
     *   ['key' => string, 'color' => string, 'points' => list<float>]
     *
     * @return array{series: list<array{key:string,color:string,points:list<float>}>, dates: list<string>}
     */
    private function buildSeries(): array
    {
        $rows = DB::table(new EventModel()->getTable())
            ->selectRaw("DATE(occurred_at) as day, variant_key, type, COUNT(DISTINCT unit_key) as unit_count")
            ->where('experiment_key', $this->experimentKey)
            ->whereIn('type', ['exposure', 'conversion'])
            ->groupByRaw('DATE(occurred_at), variant_key, type')
            ->orderBy('day')
            ->get();

        if ($rows->isEmpty()) {
            return ['series' => [], 'dates' => []];
        }

        // Index by [date][variant][type] = count
        $indexed = [];
        $allDates = [];
        $allVariants = [];

        foreach ($rows as $row) {
            $indexed[$row->day][$row->variant_key][$row->type] = (int) $row->unit_count;
            $allDates[$row->day] = true;
            $allVariants[$row->variant_key] = true;
        }

        $dates    = array_keys($allDates);
        $variants = array_keys($allVariants);
        sort($dates);

        if (count($dates) < 2) {
            return ['series' => [], 'dates' => []];
        }

        $colors = ['#94a3b8', '#8b5cf6', '#06b6d4', '#f59e0b', '#10b981', '#f43f5e'];

        // Sort variants: control first (by name), then alphabetically.
        usort($variants, static fn ($a, $b) => ($a === 'control' ? -1 : ($b === 'control' ? 1 : strcmp($a, $b))));

        $series = [];

        foreach ($variants as $i => $variantKey) {
            $cumulativeExposures   = 0;
            $cumulativeConversions = 0;
            $points = [];

            foreach ($dates as $date) {
                $cumulativeExposures   += $indexed[$date][$variantKey]['exposure'] ?? 0;
                $cumulativeConversions += $indexed[$date][$variantKey]['conversion'] ?? 0;

                $points[] = $cumulativeExposures > 0
                    ? round($cumulativeConversions / $cumulativeExposures * 100, 2)
                    : 0.0;
            }

            $series[] = [
                'key'    => $variantKey,
                'color'  => $colors[$i % count($colors)],
                'points' => $points,
            ];
        }

        return ['series' => $series, 'dates' => $dates];
    }
}
