<?php

declare(strict_types=1);

namespace ABTests\Dashboard\Livewire;

use ABTests\Infrastructure\Database\Models\AssignmentModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Dashboard home page. Aggregates system-wide health metrics and provides
 * chart-ready data for experiments-by-status, feature flag health, running
 * traffic distribution, and top experiments by assignment volume.
 */
final class DashboardIndex extends Component
{
    public function render(): View
    {
        // ── Experiments ──────────────────────────────────────────────
        $experimentsByStatus = ExperimentModel::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalExperiments = (int) $experimentsByStatus->sum();
        $runningCount     = (int) ($experimentsByStatus->get('running') ?? 0);

        $runningExperiments = ExperimentModel::query()
            ->where('status', 'running')
            ->orderByDesc('started_at')
            ->get(['key', 'traffic_percentage']);

        // ── Feature flags ─────────────────────────────────────────────
        $enabledFlagsCount  = FeatureFlagStateModel::query()->where('is_enabled', true)->whereNull('killed_at')->count();
        $disabledFlagsCount = FeatureFlagStateModel::query()->where('is_enabled', false)->whereNull('killed_at')->count();
        $killedFlagsCount   = FeatureFlagStateModel::query()->whereNotNull('killed_at')->count();
        $totalFlags         = $enabledFlagsCount + $disabledFlagsCount + $killedFlagsCount;

        // ── Assignments ───────────────────────────────────────────────
        $totalAssignments = AssignmentModel::query()->count();

        $topAssignments = AssignmentModel::query()
            ->select('experiment_key', DB::raw('count(*) as total'))
            ->groupBy('experiment_key')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        // ── Chart data ────────────────────────────────────────────────
        $statusColors = [
            'draft'     => 'rgba(107,114,128,0.85)',
            'scheduled' => 'rgba(96,165,250,0.85)',
            'running'   => 'rgba(74,222,128,0.85)',
            'paused'    => 'rgba(250,204,21,0.85)',
            'completed' => 'rgba(167,139,250,0.85)',
            'archived'  => 'rgba(55,65,81,0.85)',
        ];

        $statusChart = [
            'labels' => $experimentsByStatus->keys()->map(fn ($k) => ucfirst($k))->values()->all(),
            'data'   => $experimentsByStatus->values()->map(fn ($v) => (int) $v)->all(),
            'colors' => $experimentsByStatus->keys()->map(fn ($k) => $statusColors[$k] ?? 'rgba(156,163,175,0.85)')->values()->all(),
        ];

        $flagsChart = [
            'labels' => ['Enabled', 'Disabled', 'Killed'],
            'data'   => [$enabledFlagsCount, $disabledFlagsCount, $killedFlagsCount],
            'colors' => ['rgba(74,222,128,0.85)', 'rgba(107,114,128,0.85)', 'rgba(248,113,113,0.85)'],
        ];

        $trafficChart = [
            'labels' => $runningExperiments->pluck('key')->all(),
            'data'   => $runningExperiments->pluck('traffic_percentage')->map(fn ($v) => (int) $v)->all(),
        ];

        $assignmentsChart = [
            'labels' => $topAssignments->pluck('experiment_key')->all(),
            'data'   => $topAssignments->pluck('total')->map(fn ($v) => (int) $v)->all(),
        ];

        return view('ab-testing::livewire.dashboard-index', [
            'totalExperiments'    => $totalExperiments,
            'runningCount'        => $runningCount,
            'totalFlags'          => $totalFlags,
            'enabledFlagsCount'   => $enabledFlagsCount,
            'totalAssignments'    => $totalAssignments,
            'statusChart'         => $statusChart,
            'flagsChart'          => $flagsChart,
            'trafficChart'        => $trafficChart,
            'assignmentsChart'    => $assignmentsChart,
        ])->layout('ab-testing::layout', ['title' => 'A/B Testing — Overview']);
    }
}
