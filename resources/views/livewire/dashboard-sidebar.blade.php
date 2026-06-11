<aside class="fixed inset-y-0 left-0 z-50 flex h-screen w-64 flex-col overflow-hidden border-r border-gray-800 bg-gray-900 transition-transform duration-200 lg:sticky lg:top-0 lg:z-auto"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-gray-800 px-5">
        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-600">
            <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-semibold leading-tight text-white">A/B Testing</p>
            <p class="text-xs leading-tight text-gray-500">Dashboard</p>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto space-y-1 px-3 py-4">

        <a href="{{ route('ab-testing.index') }}"
           class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors
                  {{ request()->routeIs('ab-testing.index') ? 'bg-violet-600/20 text-violet-300' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-100' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M2.25 12 11.204 3.045c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
            </svg>
            Overview
        </a>

        <p class="px-2 pb-1 pt-4 text-xs font-semibold uppercase tracking-widest text-gray-500">Experiments</p>

        <a href="{{ route('ab-testing.experiments.index') }}"
           class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors
                  {{ request()->routeIs('ab-testing.experiments.index') || request()->routeIs('ab-testing.experiments.show') ? 'bg-violet-600/20 text-violet-300' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-100' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
            </svg>
            Experiments
        </a>

        <p class="px-2 pb-1 pt-4 text-xs font-semibold uppercase tracking-widest text-gray-500">Feature Flags</p>

        <a href="{{ route('ab-testing.feature-flags.index') }}"
           class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors
                  {{ request()->routeIs('ab-testing.feature-flags.index') || request()->routeIs('ab-testing.feature-flag.show') ? 'bg-violet-600/20 text-violet-300' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-100' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5"/>
            </svg>
            Feature Flags
        </a>
    </nav>

    <x-ab-testing::sidebar-footer />
</aside>
