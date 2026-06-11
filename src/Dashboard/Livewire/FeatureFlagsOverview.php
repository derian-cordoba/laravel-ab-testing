<?php

declare(strict_types=1);

namespace ABTests\Dashboard\Livewire;

use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Registry\FeatureFlagRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

/**
 * Full-page Livewire component for the feature flags overview. Renders a table
 * of all flags that exist in the database with their live operational state and
 * a link to each flag's detail page.
 */
final class FeatureFlagsOverview extends Component
{
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
