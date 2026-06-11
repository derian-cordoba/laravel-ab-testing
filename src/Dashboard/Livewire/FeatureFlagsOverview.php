<?php

declare(strict_types=1);

namespace ABTests\Dashboard\Livewire;

use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Registry\FeatureFlagRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Full-page Livewire component for the feature flags overview. Renders a card
 * per flag with its live operational state; re-renders whenever any embedded
 * FeatureFlagControls child dispatches 'flag-updated'.
 */
final class FeatureFlagsOverview extends Component
{
    /** Re-render so card headers reflect the updated state after any action. */
    #[On('flag-updated')]
    public function onFlagUpdated(): void {
        //
    }

    public function render(): View
    {
        $flags = FeatureFlagStateModel::query()
            ->orderBy('key')
            ->get()
            ->all();

        $registry = app(FeatureFlagRegistry::class);

        $rows = array_map(static function (FeatureFlagStateModel $model) use ($registry): array {
            $definition = null;

            try {
                $definition = $registry->findByKey($model->key);
            } catch (Throwable) {
                // Not registered in code — state record only.
            }

            return [
                'model' => $model,
                'definition' => $definition,
            ];
        }, $flags);

        return view('ab-testing::livewire.feature-flags-overview', compact('rows'))
            ->layout('ab-testing::layout', ['title' => 'A/B Testing — Feature Flags']);
    }
}
