<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Application\Registry\FeatureFlagRegistry;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
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
    public function onFlagUpdated(): void
    {
        //
    }

    public function render(): View
    {
        $flags = FeatureFlagStateModel::query()
            ->orderBy('key')
            ->get()
            ->all();

        $registry = app(FeatureFlagRegistry::class);

        /** @var int $staleThresholdDays */
        $staleThresholdDays = config('ab-testing.feature_flags.stale_threshold_days', 90);
        $staleThreshold = Carbon::now()->subDays($staleThresholdDays);

        $rows = array_map(
            static function (FeatureFlagStateModel $model) use ($registry, $staleThreshold): array {
                $definition = null;

                try {
                    $definition = $registry->findByKey($model->key);
                } catch (Throwable) {
                    // Not registered in code — state record only.
                }

                // A flag is stale when it is still enabled (active in production)
                // but has not been evaluated for longer than the configured threshold.
                // We prefer last_evaluated_at (accurate) over updated_at (proxy).
                // Killed flags and fully-disabled flags are not considered stale
                // because they are already in a "decided" state.
                $activityTimestamp = $model->last_evaluated_at ?? $model->updated_at;
                $isStale = $model->is_enabled
                    && $model->killed_at === null
                    && $activityTimestamp !== null
                    && $activityTimestamp->isBefore($staleThreshold);

                return [
                    'model' => $model,
                    'definition' => $definition,
                    'is_stale' => $isStale,
                ];
            },
            $flags,
        );

        $staleCount = count(array_filter($rows, static fn ($r) => $r['is_stale']));

        return view('ab-testing::livewire.feature-flags-overview', compact('rows', 'staleCount', 'staleThresholdDays'))
            ->layout('ab-testing::layout', ['title' => 'A/B Testing — Feature Flags']);
    }
}
