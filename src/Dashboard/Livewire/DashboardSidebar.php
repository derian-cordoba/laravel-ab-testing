<?php

declare(strict_types=1);

namespace ABTests\Dashboard\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

final class DashboardSidebar extends Component
{
    public function render(): View
    {
        return view('ab-testing::livewire.dashboard-sidebar');
    }
}
