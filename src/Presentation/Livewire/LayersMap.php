<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Infrastructure\Database\Models\AssignmentModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Renders the mutual-exclusion layer map: all named layers with the experiments
 * running inside them, assignment volume, and a warning when multiple
 * experiments in the same layer are simultaneously live.
 */
final class LayersMap extends Component
{
    public function render(): View
    {
        // All experiments, ordered: running first, then by layer + key.
        $experiments = ExperimentModel::query()
            ->orderByRaw("CASE WHEN status = 'running' THEN 0 ELSE 1 END")
            ->orderBy('layer')
            ->orderBy('key')
            ->get(['key', 'name', 'status', 'layer', 'traffic_percentage', 'started_at']);

        // Assignment counts per experiment key.
        $assignmentCounts = AssignmentModel::query()
            ->select('experiment_key', DB::raw('count(*) as total'))
            ->groupBy('experiment_key')
            ->pluck('total', 'experiment_key');

        // Assignment counts per layer.
        $layerAssignmentCounts = AssignmentModel::query()
            ->whereNotNull('layer')
            ->select('layer', DB::raw('count(*) as total'))
            ->groupBy('layer')
            ->pluck('total', 'layer');

        // Group into layers (null layer → special "No Layer" bucket).
        $layers = [];

        foreach ($experiments as $exp) {
            $layerKey = $exp->layer ?? '__none__';

            if (! isset($layers[$layerKey])) {
                $layers[$layerKey] = [
                    'name' => $exp->layer ?? null,
                    'experiments' => [],
                    'runningCount' => 0,
                    'assignments' => $exp->layer ? (int) ($layerAssignmentCounts->get($exp->layer) ?? 0) : 0,
                ];
            }

            $isRunning = $exp->status === 'running';

            $layers[$layerKey]['experiments'][] = [
                'key' => $exp->key,
                'label' => $exp->name ?? $exp->key,
                'status' => $exp->status,
                'traffic_percentage' => $exp->traffic_percentage,
                'started_at' => $exp->started_at,
                'assignments' => (int) ($assignmentCounts->get($exp->key) ?? 0),
                'isRunning' => $isRunning,
            ];

            if ($isRunning) {
                $layers[$layerKey]['runningCount']++;
            }
        }

        // Sort: named layers first (alphabetically), then "No Layer" bucket.
        uksort($layers, static fn ($a, $b) => ($a === '__none__') - ($b === '__none__') ?: strcmp($a, $b));

        return view('ab-testing::livewire.layers-map', compact('layers'))
            ->layout('ab-testing::layout', ['title' => 'A/B Testing — Layers']);
    }
}
