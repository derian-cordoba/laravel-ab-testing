<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Infrastructure\Database\Models\AssignmentModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\VariantModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * QA Overrides — manually force a specific assignment for any unit key in any
 * experiment, bypassing the deterministic bucketing algorithm. This lets QA
 * engineers test specific variants without needing a particular hash value.
 *
 * Overrides are stored directly in ab_testing_assignments. Deleting a row
 * restores natural bucketing on the unit's next resolution.
 */
final class QaOverrides extends Component
{
    // ── Create form ───────────────────────────────────────────────────────────

    public string $newExperimentKey = '';

    public string $newUnitType = 'user';

    public string $newUnitKey = '';

    public string $newVariantKey = '';

    // ── Filter ───────────────────────────────────────────────────────────────

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'exp', except: '')]
    public string $experimentSearch = '';

    public int $perPage = 25;

    // ── Actions ───────────────────────────────────────────────────────────────

    public function setOverride(): void
    {
        $this->validate([
            'newExperimentKey' => 'required|string|max:255',
            'newUnitType' => 'required|string|max:100',
            'newUnitKey' => 'required|string|max:500',
            'newVariantKey' => 'required|string|max:255',
        ]);

        AssignmentModel::query()->updateOrCreate(
            [
                'experiment_key' => $this->newExperimentKey,
                'unit_type' => $this->newUnitType,
                'unit_key' => $this->newUnitKey,
            ],
            [
                'variant_key' => $this->newVariantKey,
                'layer' => ExperimentModel::query()->where('key', $this->newExperimentKey)->value('layer'),
                'assigned_at' => Carbon::now(),
            ],
        );

        $this->reset(['newUnitKey', 'newVariantKey']);
        session()->flash('override-success', 'Override set successfully.');
    }

    public function removeOverride(string $experimentKey, string $unitType, string $unitKey): void
    {
        AssignmentModel::query()
            ->where('experiment_key', $experimentKey)
            ->where('unit_type', $unitType)
            ->where('unit_key', $unitKey)
            ->delete();

        session()->flash('override-success', 'Override removed — unit will be re-assigned on next resolution.');
    }

    public function loadMore(): void
    {
        $this->perPage += 25;
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render(): View
    {
        $experimentsForSelect = ExperimentModel::query()
            ->orderBy('key')
            ->get(['key', 'name', 'status'])
            ->map(static fn ($e): array => [
                'key' => $e->key,
                'label' => $e->name ?? $e->key,
                'status' => $e->status,
            ]);

        // Available variants for the currently selected experiment (create form).
        $variantsForSelect = $this->newExperimentKey !== ''
            ? VariantModel::query()
                ->whereHas(
                    'experiment',
                    fn ($q) => $q->where('key', $this->newExperimentKey),
                )
                ->orderByDesc('is_control')
                ->orderBy('key')
                ->pluck('key')
            : collect();

        // Assignment list with optional search.
        $query = AssignmentModel::query()->orderByDesc('assigned_at');

        if ($this->search !== '') {
            $query->where('unit_key', 'like', '%'.$this->search.'%');
        }

        if ($this->experimentSearch !== '') {
            $query->where('experiment_key', 'like', '%'.$this->experimentSearch.'%');
        }

        $total = $query->count();
        $assignments = $query->limit($this->perPage)->get();
        $hasMore = $total > $this->perPage;

        return view(
            'ab-testing::livewire.qa-overrides',
            compact('experimentsForSelect', 'variantsForSelect', 'assignments', 'total', 'hasMore'),
        )->layout('ab-testing::layout', ['title' => 'A/B Testing — QA Overrides']);
    }
}
