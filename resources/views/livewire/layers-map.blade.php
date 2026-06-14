@use(ABTests\Enums\ExperimentStatus)

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-white">Layers</h1>
        <p class="mt-1 text-sm text-gray-400">
            Mutual-exclusion namespaces — a unit enters at most one running experiment per layer.
        </p>
    </div>

    @if(empty($layers))
        <div class="rounded-lg border border-gray-700 bg-gray-800/50 p-10 text-center">
            <p class="text-sm text-gray-400">No experiments found.</p>
        </div>
    @else
        <div class="space-y-5">
            @foreach($layers as $layerKey => $layer)
                @php
                    $isNamed  = $layerKey !== '__none__';
                    $hasConflict = $isNamed && $layer['runningCount'] > 1;
                @endphp

                <div class="rounded-lg border {{ $hasConflict ? 'border-orange-700/60' : 'border-gray-700' }} bg-gray-900 overflow-hidden">

                    {{-- Layer header --}}
                    <div class="flex items-center gap-4 border-b {{ $hasConflict ? 'border-orange-700/40 bg-orange-900/10' : 'border-gray-700/60 bg-gray-800/40' }} px-5 py-4">
                        @if($isNamed)
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-900/60">
                                <svg class="h-4 w-4 text-violet-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-100">{{ $layer['name'] }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ count($layer['experiments']) }} {{ count($layer['experiments']) === 1 ? 'experiment' : 'experiments' }}
                                    @if($layer['assignments'] > 0)
                                        &middot; {{ number_format($layer['assignments']) }} assignments
                                    @endif
                                </p>
                            </div>
                        @else
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-800">
                                <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-400">No Layer</p>
                                <p class="text-xs text-gray-600">Experiments without a mutual-exclusion layer</p>
                            </div>
                        @endif

                        <div class="flex items-center gap-2 shrink-0">
                            @if($layer['runningCount'] > 0)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-green-900/50 px-2.5 py-1 text-xs font-medium text-green-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-green-400"></span>
                                    {{ $layer['runningCount'] }} running
                                </span>
                            @endif
                            @if($hasConflict)
                                <span class="inline-flex items-center gap-1 rounded-full bg-orange-900/50 px-2.5 py-1 text-xs font-medium text-orange-400">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                                    </svg>
                                    Layer conflict
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($hasConflict)
                        <div class="px-5 py-2 bg-orange-900/10 border-b border-orange-700/30">
                            <p class="text-xs text-orange-400/90">
                                Multiple experiments are simultaneously running in this layer. A unit will only be assigned to the first experiment it qualifies for, which may cause unexpected bucketing — consider pausing or archiving conflicting experiments.
                            </p>
                        </div>
                    @endif

                    {{-- Experiment rows --}}
                    <div class="divide-y divide-gray-700/40">
                        @foreach($layer['experiments'] as $exp)
                            @php $statusEnum = ExperimentStatus::tryFrom($exp['status']); @endphp
                            <div class="flex items-center gap-4 px-5 py-3.5 hover:bg-gray-800/30 transition-colors">
                                {{-- Status dot --}}
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center">
                                    <span class="h-2 w-2 rounded-full {{ $statusEnum?->dotClass() ?? 'bg-gray-500' }}"></span>
                                </span>

                                {{-- Name + key --}}
                                <div class="flex-1 min-w-0">
                                    <a href="{{ route('ab-testing.experiments.show', $exp['key']) }}"
                                       class="text-sm font-medium text-gray-200 hover:text-violet-300 transition-colors truncate block">
                                        {{ $exp['label'] }}
                                    </a>
                                    <p class="font-mono text-xs text-gray-600 truncate">{{ $exp['key'] }}</p>
                                </div>

                                {{-- Traffic --}}
                                <span class="hidden sm:inline-flex items-center rounded bg-gray-800 px-2 py-0.5 text-xs tabular-nums text-gray-500 border border-gray-700">
                                    {{ $exp['traffic_percentage'] }}% traffic
                                </span>

                                {{-- Assignments --}}
                                <span class="hidden md:block text-xs tabular-nums text-gray-600 whitespace-nowrap">
                                    {{ number_format($exp['assignments']) }} assigned
                                </span>

                                {{-- Started --}}
                                @if($exp['started_at'])
                                    <span class="hidden lg:block text-xs text-gray-700 whitespace-nowrap">
                                        {{ $exp['started_at']->diffForHumans() }}
                                    </span>
                                @endif

                                <x-ab-testing::status-badge :status="$exp['status']" />
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
