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

    <nav class="flex-1 overflow-y-auto space-y-0.5 px-3 py-4">

        <a href="{{ route('ab-testing.index') }}"
           class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors
                  {{ request()->routeIs('ab-testing.index') ? 'bg-violet-600/20 text-violet-300' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-100' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M2.25 12 11.204 3.045c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
            </svg>
            Overview
        </a>

        {{-- ── Experiments ─────────────────────────────────────── --}}
        <p class="px-2 pb-1 pt-5 text-xs font-semibold uppercase tracking-widest text-gray-600">Experiments</p>

        <a href="{{ route('ab-testing.experiments.index') }}"
           class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors
                  {{ request()->routeIs('ab-testing.experiments.index') || request()->routeIs('ab-testing.experiments.show') ? 'bg-violet-600/20 text-violet-300' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-100' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
            </svg>
            <span class="flex-1">Experiments</span>
            @if($runningCount > 0)
                <span class="inline-flex items-center rounded-full bg-green-900/50 px-1.5 py-0.5 text-xs font-medium tabular-nums text-green-400"
                      title="{{ $runningCount }} running">{{ $runningCount }}</span>
            @endif
            @if($activeBreachCount > 0)
                <span class="inline-flex items-center rounded-full bg-red-900/60 px-1.5 py-0.5 text-xs font-medium text-red-400"
                      title="{{ $activeBreachCount }} guardrail {{ $activeBreachCount === 1 ? 'breach' : 'breaches' }}">⚠</span>
            @endif
        </a>

        <a href="{{ route('ab-testing.layers') }}"
           class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors
                  {{ request()->routeIs('ab-testing.layers') ? 'bg-violet-600/20 text-violet-300' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-100' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3"/>
            </svg>
            Layers
        </a>

        <a href="{{ route('ab-testing.metrics') }}"
           class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors
                  {{ request()->routeIs('ab-testing.metrics') ? 'bg-violet-600/20 text-violet-300' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-100' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
            </svg>
            Metrics
        </a>

        <a href="{{ route('ab-testing.segments') }}"
           class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors
                  {{ request()->routeIs('ab-testing.segments') ? 'bg-violet-600/20 text-violet-300' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-100' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
            </svg>
            Segments
        </a>

        {{-- ── Feature Flags ───────────────────────────────────── --}}
        <p class="px-2 pb-1 pt-5 text-xs font-semibold uppercase tracking-widest text-gray-600">Feature Flags</p>

        <a href="{{ route('ab-testing.feature-flags.index') }}"
           class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors
                  {{ request()->routeIs('ab-testing.feature-flags.index') || request()->routeIs('ab-testing.feature-flag.show') ? 'bg-violet-600/20 text-violet-300' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-100' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5"/>
            </svg>
            <span class="flex-1">Feature Flags</span>
            @if($enabledFlagsCount > 0)
                <span class="inline-flex items-center rounded-full bg-green-900/50 px-1.5 py-0.5 text-xs font-medium tabular-nums text-green-400">{{ $enabledFlagsCount }}</span>
            @endif
        </a>

        {{-- ── Governance ──────────────────────────────────────── --}}
        <p class="px-2 pb-1 pt-5 text-xs font-semibold uppercase tracking-widest text-gray-600">Governance</p>

        <a href="{{ route('ab-testing.audit-log') }}"
           class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors
                  {{ request()->routeIs('ab-testing.audit-log') ? 'bg-violet-600/20 text-violet-300' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-100' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/>
            </svg>
            Audit Log
        </a>

        <a href="{{ route('ab-testing.qa-overrides') }}"
           class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors
                  {{ request()->routeIs('ab-testing.qa-overrides') ? 'bg-violet-600/20 text-violet-300' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-100' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.654-4.654m5.598-2.231 3.03-2.496a1.873 1.873 0 0 0-2.228-2.973M16.5 9l-3.03 2.496m0 0L11.42 15.17m2.05-3.671a2.25 2.25 0 0 0-3.172 3.172"/>
            </svg>
            QA Overrides
        </a>

    </nav>

    <x-ab-testing::sidebar-footer />
</aside>
