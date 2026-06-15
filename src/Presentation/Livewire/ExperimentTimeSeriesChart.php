<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Infrastructure\Database\Models\EventModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\VariantModel;
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
        ['series' => $series, 'dates' => $dates, 'boundaries' => $boundaries] = $this->buildSeries();

        if ($this->shouldDispatchRefresh) {
            // Dispatched after the DOM morph commits, so Alpine can safely read
            // the updated carrier element when it receives the browser event.
            $this->dispatch('chart-data-refreshed', hasData: !empty($series) && count($dates) >= 2);
            $this->shouldDispatchRefresh = false;
        }

        return view('ab-testing::livewire.experiment-time-series-chart', compact('series', 'dates', 'boundaries'));
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Returns per-variant cumulative conversion-rate series, sorted date labels,
     * and optional O'Brien-Fleming sequential testing boundary lines.
     *
     * Each series entry: ['key' => string, 'color' => string, 'points' => list<float>]
     * Boundaries (null when target_sample_size is not set):
     *   ['upper' => list<float|null>, 'lower' => list<float|null>]
     *
     * @return array{
     *   series: list<array{key:string,color:string,points:list<float>}>,
     *   dates: list<string>,
     *   boundaries: array{upper:list<float|null>,lower:list<float|null>}|null
     * }
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
            return ['series' => [], 'dates' => [], 'boundaries' => null];
        }

        // Index by [date][variant][type] = count
        $indexed     = [];
        $allDates    = [];
        $allVariants = [];

        foreach ($rows as $row) {
            $indexed[$row->day][$row->variant_key][$row->type] = (int) $row->unit_count;
            $allDates[$row->day]    = true;
            $allVariants[$row->variant_key] = true;
        }

        $dates    = array_keys($allDates);
        $variants = array_keys($allVariants);
        sort($dates);

        if (count($dates) < 2) {
            return ['series' => [], 'dates' => [], 'boundaries' => null];
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

        $boundaries = $this->buildSequentialBoundary($indexed, $dates, $variants);

        return ['series' => $series, 'dates' => $dates, 'boundaries' => $boundaries];
    }

    /**
     * Compute O'Brien-Fleming sequential testing boundaries for the conversion
     * rate chart. At information fraction τ = n_control / target_per_arm the
     * critical z-score is z_α/2 / √τ, giving boundary lines that tighten as
     * sample size grows. Returns null when target_sample_size is not set.
     *
     * @param  array<string, array<string, array<string, int>>> $indexed   [date][variant][type] → count
     * @param  list<string>                                     $dates     sorted date strings
     * @param  list<string>                                     $variants  variant keys (control first)
     * @return array{upper:list<float|null>,lower:list<float|null>}|null
     */
    private function buildSequentialBoundary(array $indexed, array $dates, array $variants): ?array
    {
        $experiment = ExperimentModel::query()
            ->where('key', $this->experimentKey)
            ->first(['target_sample_size']);

        $targetSampleSize = $experiment?->target_sample_size;

        if (! $targetSampleSize || count($variants) < 2) {
            return null;
        }

        // Identify the control key from the variants snapshot; fall back to $variants[0].
        $controlKey = VariantModel::query()
            ->whereHas('experiment', fn ($q) => $q->where('key', $this->experimentKey))
            ->where('is_control', true)
            ->value('key') ?? $variants[0];

        $numArms      = count($variants);
        $targetPerArm = $targetSampleSize / $numArms;
        $zAlpha2      = 1.959964; // z_{0.025}

        $cumulativeControlN           = 0;
        $cumulativeControlConversions = 0;
        $boundaryUpper                = [];
        $boundaryLower                = [];

        foreach ($dates as $date) {
            $cumulativeControlN           += $indexed[$date][$controlKey]['exposure']   ?? 0;
            $cumulativeControlConversions += $indexed[$date][$controlKey]['conversion'] ?? 0;

            if ($cumulativeControlN < 5) {
                // Too few data points — suppress boundary to avoid visual noise.
                $boundaryUpper[] = null;
                $boundaryLower[] = null;
                continue;
            }

            $tau          = $cumulativeControlN / $targetPerArm;
            // O'Brien-Fleming: z_t = z_α/2 / √τ; cap at z_α/2 once fully powered.
            $zBoundary    = $tau < 1.0 ? min(10.0, $zAlpha2 / sqrt($tau)) : $zAlpha2;
            $controlRate  = $cumulativeControlConversions / $cumulativeControlN;
            $se           = $controlRate > 0.0 && $controlRate < 1.0
                ? sqrt($controlRate * (1.0 - $controlRate) / $cumulativeControlN)
                : 0.0;

            $boundaryUpper[] = round(min(100.0, ($controlRate + $zBoundary * $se) * 100.0), 3);
            $boundaryLower[] = round(max(0.0,   ($controlRate - $zBoundary * $se) * 100.0), 3);
        }

        return ['upper' => $boundaryUpper, 'lower' => $boundaryLower];
    }
}
