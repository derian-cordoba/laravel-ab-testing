<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Enums\ExperimentStatus;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\GuardrailBreachModel;
use ABTests\Application\Registry\ExperimentRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Full-page Livewire component for a single experiment's detail view.
 * Loads the ExperimentModel + recent audit log entries for the header and
 * history panel. Results are delegated to ExperimentResultsTable and lifecycle
 * actions to ExperimentControls.
 */
final class ExperimentDetail extends Component
{
    public string $key = '';

    public function mount(string $key): void
    {
        $this->key = $key;
    }

    #[On('experiment-updated')]
    public function refresh(): void
    {
        // Triggers a re-render so the audit log and header update after
        // every lifecycle command dispatched by ExperimentControls.
    }

    /**
     * Called by wire:poll.30s when the experiment is running. Re-dispatches
     * the experiment-updated event so all child components refresh their data.
     */
    public function pollRefresh(): void
    {
        $status = ExperimentModel::query()
            ->where('key', $this->key)
            ->value('status');

        if ($status === ExperimentStatus::running->value) {
            $this->dispatch('experiment-updated');
        }
    }

    public function render(): View
    {
        $model = ExperimentModel::query()->firstWhere('key', $this->key);

        $displayName = $this->key;
        $isRunning   = false;

        if ($model !== null) {
            $isRunning = $model->status === ExperimentStatus::running->value;

            try {
                $definition  = app(ExperimentRegistry::class)->findByKey($this->key);
                $displayName = $definition->name ?? $model->name ?? $this->key;
            } catch (Throwable) {
                $displayName = $model->name ?? $model->key;
            }
        }

        /** @var Collection<int, AuditLogModel> $auditLog */
        $auditLog = AuditLogModel::query()
            ->where('experiment_key', $this->key)
            ->orderByDesc('occurred_at')
            ->limit(10)
            ->get();

        $activeBreachCount = GuardrailBreachModel::query()
            ->where('experiment_key', $this->key)
            ->where('is_acknowledged', false)
            ->count();

        return view('ab-testing::livewire.experiment-detail', compact('model', 'displayName', 'auditLog', 'isRunning', 'activeBreachCount'))
            ->layout('ab-testing::layout', [
                'title' => 'A/B Testing — ' . $displayName,
            ]);
    }
}
