<div>
    {{-- Page header --}}
    <div class="mb-7 sm:flex sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-white">Metrics Catalog</h1>
            <p class="mt-1 text-sm text-gray-400">
                Every metric referenced across registered experiments — configuration and usage at a glance.
            </p>
        </div>
        <p class="mt-3 text-xs text-gray-600 sm:mt-0">{{ count($metrics) }} {{ count($metrics) === 1 ? 'metric' : 'metrics' }}</p>
    </div>

    {{-- Search --}}
    <div class="mb-5">
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-500 pointer-events-none"
                 fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 15.803a7.5 7.5 0 0 0 10.607 0Z"/>
            </svg>
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search by key, event, or type…"
                class="block w-full rounded-lg border border-gray-700 bg-gray-900 py-2.5 pl-9 pr-4 text-sm text-gray-100 placeholder-gray-500 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none"
            >
        </div>
    </div>

    @if(empty($metrics))
        <x-ab-testing::empty-state
            message="{{ $search ? 'No metrics match your search.' : 'No metrics found.' }}"
            hint="{{ $search ? 'Try a different search term.' : 'Register experiments with #[AsMetric] to populate this catalog.' }}"
        />
    @else
        <div class="space-y-3">
            @foreach($metrics as $key => $metric)
                @php
                    $metricUsages = $usages[$metric['key']] ?? $usages[$key] ?? [];
                    $roleColors = [
                        'primary'   => 'bg-violet-900/50 text-violet-300',
                        'secondary' => 'bg-gray-700/60 text-gray-300',
                        'guardrail' => 'bg-orange-900/50 text-orange-300',
                    ];
                @endphp

                <div class="rounded-lg border border-gray-700 bg-gray-900 overflow-hidden">

                    {{-- Header row --}}
                    <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-700/50">
                        <div class="min-w-0 flex-1">
                            <div x-data="{ copied: false }" class="flex items-center gap-2">
                                <code class="font-mono text-sm text-violet-300">{{ $metric['key'] }}</code>
                                <button
                                    type="button"
                                    title="Copy key"
                                    @click="navigator.clipboard && navigator.clipboard.writeText('{{ $metric['key'] }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                    class="shrink-0 rounded p-0.5 text-gray-600 hover:text-gray-300 transition-colors"
                                >
                                    <svg x-show="!copied" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"/>
                                    </svg>
                                    <svg x-show="copied" class="h-3.5 w-3.5 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                    </svg>
                                </button>
                            </div>
                            @if($metric['class'])
                                <p class="mt-0.5 font-mono text-xs text-gray-600 truncate">{{ $metric['class'] }}</p>
                            @endif
                        </div>

                        {{-- Type badge --}}
                        @if($metric['type'])
                            <span class="inline-flex items-center shrink-0 rounded bg-gray-800 border border-gray-700 px-2 py-0.5 text-xs font-mono text-gray-300">
                                {{ $metric['type'] }}
                            </span>
                        @endif
                    </div>

                    {{-- Config + Usages --}}
                    <div class="grid grid-cols-1 gap-0 divide-y divide-gray-700/40 lg:grid-cols-2 lg:divide-x lg:divide-y-0">

                        {{-- Configuration --}}
                        <div class="px-5 py-4 space-y-2">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 mb-3">Configuration</p>

                            @if($metric['event'])
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="text-xs text-gray-600 w-20 shrink-0">Event</span>
                                    <code class="font-mono text-xs text-gray-300">{{ $metric['event'] }}</code>
                                </div>
                            @endif

                            @if($metric['aggregate'])
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="text-xs text-gray-600 w-20 shrink-0">Aggregate</span>
                                    <span class="text-xs text-gray-300">{{ $metric['aggregate'] }}</span>
                                </div>
                            @endif

                            @if($metric['window'])
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="text-xs text-gray-600 w-20 shrink-0">Window</span>
                                    <span class="text-xs text-gray-300">{{ $metric['window'] }}</span>
                                </div>
                            @endif

                            @if(!$metric['event'] && !$metric['aggregate'] && !$metric['window'])
                                <p class="text-xs text-gray-600">Configuration derived from database or not available.</p>
                            @endif
                        </div>

                        {{-- Usages --}}
                        <div class="px-5 py-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 mb-3">
                                Used in {{ count($metricUsages) }} {{ count($metricUsages) === 1 ? 'experiment' : 'experiments' }}
                            </p>

                            @if(empty($metricUsages))
                                <p class="text-xs text-gray-600">Not yet used in any experiment.</p>
                            @else
                                <div class="space-y-2">
                                    @foreach($metricUsages as $usage)
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('ab-testing.experiments.show', $usage['experimentKey']) }}"
                                               class="flex-1 text-xs text-gray-300 hover:text-violet-300 transition-colors truncate">
                                                {{ $usage['experimentName'] }}
                                            </a>
                                            <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $roleColors[$usage['role']->value] ?? 'bg-gray-700 text-gray-400' }}">
                                                {{ $usage['role']->value }}
                                            </span>
                                            @if($usage['role']->value === 'guardrail' && $usage['maximumRegression'] !== null)
                                                <span class="shrink-0 text-xs text-orange-400/70">
                                                    max {{ number_format($usage['maximumRegression'] * 100, 1) }}% regression
                                                </span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
