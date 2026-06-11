<?php

declare(strict_types=1);

namespace ABTests\Dashboard\Livewire;

use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

final class DashboardSidebar extends Component
{
    #[On('experiment-updated')]
    public function refreshSidebar(): void
    {
    }

    public function render(): View
    {
        $experiments = ExperimentModel::query()
            ->orderByDesc('created_at')
            ->get(['key', 'status']);

        return view('ab-testing::livewire.dashboard-sidebar', compact('experiments'));
    }
}
