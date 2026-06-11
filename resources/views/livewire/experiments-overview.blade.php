@use(ABTests\Enums\ExperimentStatus)
@use(ABTests\Infrastructure\Database\Models\ExperimentModel)

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-white">Experiments</h1>
        <p class="mt-1 text-sm text-gray-400">All registered experiments and their current operational state.</p>
    </div>

    @if(empty($rows))
        <div class="rounded-lg border border-gray-700 bg-gray-800/50 p-8 text-center">
            <p class="text-sm text-gray-300">No experiments found in the database yet.</p>
            <p class="mt-1 text-xs text-gray-500">Register experiments in your config or run migrations to get
                started.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($rows as $row)
                @php
                    /** @var ExperimentModel $model */
                    $model = $row['model'];
                    $definition = $row['definition'];
                    $displayName = $definition?->name ?? $model->key;
                    $statusEnum = ExperimentStatus::tryFrom($model->status);
                @endphp

                <div x-data="{ open: false }"
                     class="rounded-lg border border-gray-700 bg-gray-900 overflow-hidden">

                    {{-- Card header (always visible) --}}
                    <button type="button"
                            @click="open = !open"
                            class="w-full flex items-center gap-4 px-5 py-4 text-left hover:bg-gray-800/40 transition-colors">

                        {{-- Status dot --}}
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center">
                            <span class="h-2 w-2 rounded-full {{ $statusEnum?->dotClass() ?? 'bg-gray-500' }}"></span>
                        </span>

                        {{-- Name + key --}}
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-100 truncate">{{ $displayName }}</p>
                            <p class="text-xs font-mono text-gray-500 truncate">{{ $model->key }}</p>
                        </div>

                        {{-- Status badge --}}
                        <x-ab-testing::status-badge :status="$model->status"/>

                        {{-- Traffic chip --}}
                        <span class="hidden sm:inline-flex items-center rounded bg-gray-800 px-2 py-0.5 text-xs tabular-nums text-gray-400 border border-gray-700">
                            {{ $model->traffic_percentage }}% traffic
                        </span>

                        {{-- Kill switch chip --}}
                        @if($model->is_killed)
                            <span class="hidden md:inline-flex items-center rounded-full bg-red-900/60 px-2.5 py-0.5 text-xs font-medium text-red-300">
                                Killed
                            </span>
                        @endif

                        {{-- Layer chip --}}
                        @if($definition?->layer)
                            <span class="hidden lg:inline-flex items-center rounded bg-gray-800 px-2 py-0.5 text-xs text-gray-500 border border-gray-700">
                                {{ $definition->layer }}
                            </span>
                        @endif

                        {{-- Assigned count --}}
                        <span class="hidden lg:block text-xs tabular-nums text-gray-500 whitespace-nowrap">
                            {{ number_format($assignedCounts[$model->key] ?? 0) }} assigned
                        </span>

                        {{-- Started --}}
                        <span class="hidden xl:block text-xs text-gray-600 whitespace-nowrap">
                            {{ $model->started_at?->diffForHumans() ?? 'Not started' }}
                        </span>

                        {{-- Edit link --}}
                        <a href="{{ route('ab-testing.experiments.show', $model->key) }}"
                           @click.stop
                           class="hidden sm:inline-flex items-center gap-1 rounded-md border border-gray-700 bg-gray-800 px-2.5 py-1 text-xs text-gray-400 hover:border-violet-600 hover:text-violet-300 transition-colors whitespace-nowrap"
                           title="Edit {{ $model->key }}">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                            </svg>
                            Edit
                        </a>

                        {{-- Chevron --}}
                        <svg class="h-4 w-4 shrink-0 text-gray-500 transition-transform duration-150"
                             :class="open ? 'rotate-180' : ''"
                             fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
                        </svg>
                    </button>

                    {{-- Expanded controls panel --}}
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1"
                         class="border-t border-gray-700/60">

                        {{-- Archived notice --}}
                        @if($statusEnum === ExperimentStatus::archived)
                            <div class="flex items-center gap-2.5 border-b border-gray-700/60 bg-gray-800/40 px-5 py-3">
                                <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24"
                                     stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                                </svg>
                                <p class="text-xs text-gray-500">
                                    This experiment is <strong class="text-gray-400">archived</strong> and read-only. No
                                    further lifecycle actions are available.
                                </p>
                            </div>
                        @endif

                        {{-- Controls (hidden for archived experiments) --}}
                        @if($statusEnum !== ExperimentStatus::archived)
                            <div class="px-5 py-5">
                                <div class="flex items-center justify-between mb-4">
                                    <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wide">
                                        Controls</h2>
                                    <a href="{{ route('ab-testing.experiments.show', $model->key) }}"
                                       class="text-xs text-violet-400 hover:text-violet-200 transition-colors">
                                        Full detail →
                                    </a>
                                </div>
                                @livewire('ab-testing::experiment-controls', [
                                    'experimentKey' => $model->key,
                                    'showKillSwitch' => false,
                                    'showData' => false,
                                    'showTrafficRamp' => false,
                                ], 'controls-' . $model->key)
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
