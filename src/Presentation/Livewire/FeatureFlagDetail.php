<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Application\Registry\FeatureFlagRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Full-page Livewire component for a single feature flag's detail view. Loads
 * the FeatureFlagStateModel and definition (if registered) for the header panel.
 * Controls are delegated to the FeatureFlagControls subcomponent.
 */
final class FeatureFlagDetail extends Component
{
    public string $key = '';

    public function mount(string $key): void
    {
        $this->key = $key;
    }

    #[On('flag-updated')]
    public function refresh(): void
    {
        // Triggers a re-render so the header reflects the latest state after
        // every command dispatched by FeatureFlagControls.
    }

    public function render(): View
    {
        $model = FeatureFlagStateModel::query()->firstWhere('key', $this->key);

        $displayName = $this->key;

        if ($model !== null) {
            try {
                $definition = app(FeatureFlagRegistry::class)->findByKey($this->key);
                $displayName = $definition->name ?? $this->key;
            } catch (Throwable) {
                $displayName = $model->key;
            }
        }

        return view('ab-testing::livewire.feature-flag-detail', compact('model', 'displayName'))
            ->layout('ab-testing::layout', [
                'title' => 'A/B Testing — ' . $displayName,
            ]);
    }
}
