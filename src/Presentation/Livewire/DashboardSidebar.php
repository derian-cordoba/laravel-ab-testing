<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Infrastructure\Database\Models\GuardrailBreachModel;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class DashboardSidebar extends Component
{
    public function render(): View
    {
        $runningCount      = ExperimentModel::query()->where('status', 'running')->count();
        $activeBreachCount = GuardrailBreachModel::query()->where('is_acknowledged', false)->count();
        $enabledFlagsCount = FeatureFlagStateModel::query()->where('is_enabled', true)->whereNull('killed_at')->count();

        return view(
            'ab-testing::livewire.dashboard-sidebar',
            compact('runningCount', 'activeBreachCount', 'enabledFlagsCount'),
        );
    }
}
