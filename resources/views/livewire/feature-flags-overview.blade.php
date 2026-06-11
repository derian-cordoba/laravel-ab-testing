@use(ABTests\Infrastructure\Database\Models\FeatureFlagStateModel)

<div>
    <div class="mb-6 sm:flex sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-white">Feature Flags</h1>
            <p class="mt-1 text-sm text-gray-400">All registered feature flags and their current operational state.</p>
        </div>
        @if($staleCount > 0)
            <span class="mt-3 sm:mt-0 inline-flex items-center gap-1.5 rounded-full bg-amber-900/50 border border-amber-700/60 px-3 py-1.5 text-xs font-medium text-amber-300">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                </svg>
                {{ $staleCount }} stale {{ $staleCount === 1 ? 'flag' : 'flags' }} — unchanged for {{ $staleThresholdDays }}+ days
            </span>
        @endif
    </div>

    @if(empty($rows))
        <div class="rounded-lg border border-gray-700 bg-gray-800/50 p-8 text-center">
            <p class="text-sm text-gray-300">No feature flags found in the database yet.</p>
            <p class="mt-1 text-xs text-gray-500">Register flags in <code
                        class="text-violet-400">config/ab-testing.php</code> and run <code class="text-violet-400">php
                    artisan ab:cache</code>.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($rows as $row)
                @php
                    /** @var FeatureFlagStateModel $model */
                    $model = $row['model'];
                    $definition = $row['definition'];
                    $displayName = $definition?->name ?? $model->key;
                    $conditionCount = count($model->conditions ?? []);
                    $isStale = $row['is_stale'];
                @endphp
                <div x-data="{ open: false }"
                     class="rounded-lg border border-gray-700 bg-gray-900 overflow-hidden">

                    {{-- Card header (always visible) --}}
                    <button type="button"
                            @click="open = !open"
                            class="w-full flex items-center gap-4 px-5 py-4 text-left hover:bg-gray-800/40 transition-colors">

                        {{-- Status dot --}}
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center">
                            @if($model->killed_at !== null)
                                <span class="h-2 w-2 rounded-full bg-red-400"></span>
                            @elseif($model->is_enabled)
                                <span class="h-2 w-2 rounded-full bg-green-400"></span>
                            @else
                                <span class="h-2 w-2 rounded-full bg-gray-600"></span>
                            @endif
                        </span>

                        {{-- Name + key --}}
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-100 truncate">{{ $displayName }}</p>
                            <p class="text-xs font-mono text-gray-500 truncate">{{ $model->key }}</p>
                        </div>

                        {{-- Stale badge --}}
                        @if($isStale)
                            <span class="hidden sm:inline-flex items-center gap-1 rounded-full bg-amber-900/50 border border-amber-700/60 px-2 py-0.5 text-xs text-amber-300">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                     stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                </svg>
                                stale
                            </span>
                        @endif

                        {{-- Status badge --}}
                        <x-ab-testing::flag-status-badge
                                :enabled="$model->is_enabled"
                                :killed="$model->killed_at !== null"
                        />

                        {{-- Rollout chip --}}
                        <span class="hidden sm:inline-flex items-center rounded bg-gray-800 px-2 py-0.5 text-xs tabular-nums text-gray-400 border border-gray-700">
                            {{ $model->rollout_percentage }}% rollout
                        </span>

                        {{-- Conditions chip --}}
                        @if($conditionCount > 0)
                            <span class="hidden md:inline-flex items-center rounded bg-violet-900/40 px-2 py-0.5 text-xs text-violet-400 border border-violet-800/60">
                                {{ $conditionCount }} {{ Str::plural('condition', $conditionCount) }}
                            </span>
                        @endif

                        {{-- Last updated --}}
                        <span class="hidden lg:block text-xs text-gray-600 whitespace-nowrap">
                            {{ $model->updated_at?->diffForHumans() ?? '—' }}
                        </span>

                        {{-- Edit link --}}
                        <a href="{{ route('ab-testing.feature-flag.show', $model->key) }}"
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
                         class="border-t border-gray-700/60 px-5 py-5">
                        @livewire('ab-testing::feature-flag-controls', [
                            'flagKey' => $model->key,
                            'showKillSwitch' => false,
                            'showRolloutPercentage' => false,
                        ], 'flag-' . $model->key)
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
