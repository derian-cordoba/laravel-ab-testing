<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Definitions\ExperimentDefinition;
use ABTests\Infrastructure\Database\Models\AssignmentModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Application\Registry\ExperimentRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Full-page Livewire component for the experiments overview. Renders a card
 * per experiment with its live operational state; re-renders whenever any
 * embedded ExperimentControls child dispatches 'experiment-updated'.
 */
final class ExperimentsOverview extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    /** Re-render so card headers reflect the updated state after any action. */
    #[On('experiment-updated')]
    public function onExperimentUpdated(): void {
        //
    }

    public function render(): View
    {
        $query = ExperimentModel::query()->orderByDesc('created_at');

        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $query->where(static fn ($q) => $q
                ->where('key', 'like', $term)
                ->orWhere('name', 'like', $term)
            );
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        $experiments = $query->get()->all();

        $registry = app(ExperimentRegistry::class);

        $rows = array_map(static function (ExperimentModel $model) use ($registry): array {
            $definition = null;

            try {
                $definition = $registry->findByKey($model->key);
            } catch (Throwable) {
                // Not registered in code — runtime-defined only.
            }

            return [
                'model'       => $model,
                'definition'  => $definition,
                'variants'    => self::normalizeVariants($model, $definition),
                'displayName' => $definition?->name ?? $model->name ?? $model->key,
            ];
        }, $experiments);

        /** @var array<string, int> $assignedCounts */
        $assignedCounts = AssignmentModel::query()
            ->select('experiment_key', DB::raw('count(*) as total'))
            ->groupBy('experiment_key')
            ->pluck('total', 'experiment_key')
            ->all();

        return view('ab-testing::livewire.experiments-overview', compact('rows', 'assignedCounts'))
            ->layout('ab-testing::layout', ['title' => 'A/B Testing — Experiments']);
    }

    /**
     * @return list<array{key: string, weight: int, is_control: bool, source: string}>
     */
    private static function normalizeVariants(ExperimentModel $model, ?ExperimentDefinition $definition): array
    {
        $dbVariants = $model->variants()
            ->orderByDesc('is_control')
            ->orderBy('key')
            ->get();

        if ($dbVariants->isNotEmpty()) {
            return $dbVariants->map(static fn ($v): array => [
                'key'        => $v->key,
                'weight'     => $v->weight,
                'is_control' => $v->is_control,
                'source'     => 'database',
            ])->all();
        }

        if ($definition !== null) {
            $sorted = $definition->allocation->variants;
            usort($sorted, static fn ($a, $b): int => $b->isControl() <=> $a->isControl() ?: strcmp($a->key(), $b->key()));

            return array_map(static fn ($v): array => [
                'key'        => $v->key(),
                'weight'     => $v->weight(),
                'is_control' => $v->isControl(),
                'source'     => 'definition',
            ], $sorted);
        }

        return [];
    }
}
